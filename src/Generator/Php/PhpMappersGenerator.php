<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\MappingStrategyResolver;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Workbench\Str;

class PhpMappersGenerator implements GeneratorInterface
{
    public function getName(): string
    {
        return 'php.mappers';
    }

    public function getDescription(): string
    {
        return 'Generates PHP mapper classes with fromArray/toArray methods';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generate(Definition $definition, array $options = []): GeneratorResult
    {
        $result = new GeneratorResult();
        $includeComments = (bool) ($options['include_comments'] ?? false);
        $namespace = $options['namespace'] ?? null;

        $ctx = new PhpMappersContext();
        $ctx->mapFrom = $options['map_from'] ?? null;
        $ctx->mapTo = $options['map_to'] ?? null;

        foreach ($definition->getPublicTypeAliases() as $aliasName => $aliasData) {
            $resolved = $aliasData->getResolvedType();
            if ($resolved instanceof Type && $resolved->isReference()) {
                $structName = $resolved->getName();
                if ($structName !== null) {
                    $mapperName = Str::toPascalCase($aliasName);
                    $ctx->pubStructToMapper[$structName] = $mapperName;
                }
            }
        }

        foreach ($ctx->pubStructToMapper as $structName => $mapperName) {
            $struct = $definition->getStruct($structName);
            if ($struct !== null) {
                $code = $this->generateMapper($ctx, $struct, $definition, $namespace, $includeComments, $mapperName);
                $result->addFile($mapperName . 'Mapper.php', $code);
            }
        }

        foreach ($definition->getModels() as $struct) {
            $code = $this->generateMapper($ctx, $struct, $definition, $namespace, $includeComments);
            $result->addFile($struct->getName() . 'Mapper.php', $code);
        }

        return $result;
    }

    private function generateMapper(PhpMappersContext $ctx, Struct $struct, Definition $definition, ?string $namespace, bool $includeComments, ?string $nameOverride = null): string
    {
        $name = $nameOverride ?? str_replace('/', '', $struct->getName());
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        if ($namespace !== null) {
            $lines[] = 'namespace ' . rtrim($namespace, '\\') . ';';
            $lines[] = '';
        }
        $lines[] = "class {$name}Mapper";
        $lines[] = '{';
        $lines[] = $this->generateMethod($ctx, 'fromArray', $struct, $definition, $includeComments);
        $lines[] = '';
        $lines[] = $this->generateMethod($ctx, 'toArray', $struct, $definition, $includeComments);
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateMethod(PhpMappersContext $ctx, string $direction, Struct $struct, Definition $definition, bool $includeComments): string
    {
        $lines = [];
        $lines[] = "    public static function {$direction}(array \$data): array";
        $lines[] = '    {';
        $lines[] = '        $result = [];';

        foreach ($struct->getProperties() as $prop) {
            $lines[] = '';
            if ($includeComments && $prop->getComment() !== null) {
                foreach (explode("\n", $prop->getComment()) as $commentLine) {
                    $lines[] = '        // ' . $commentLine;
                }
            }

            $fromKey = MappingStrategyResolver::resolve($definition,$ctx->mapFrom ?? 'self', $prop->getName(), $prop->getAnnotations());
            $toKey = MappingStrategyResolver::resolve($definition,$ctx->mapTo ?? 'self', $prop->getName(), $prop->getAnnotations());
            $readKey = ($direction === 'fromArray') ? $toKey : $fromKey;
            $writeKey = ($direction === 'fromArray') ? $fromKey : $toKey;

            $varAccess = "\$data['{$readKey}']";
            try {
                $cast = $this->generateCast($ctx, $prop->getType(), $varAccess, $definition, $direction);
            } catch (GeneratorException $e) {
                throw (new GeneratorException($e->getMessage(), 0, $e))
                    ->setStructContext($struct->getName(), $prop->getName());
            }

            if ($prop->isOptional()) {
                $lines[] = "        if (array_key_exists('{$readKey}', \$data)) {";
                $lines[] = '            $result[\'' . $writeKey . '\'] = ' . $cast . ';';
                $lines[] = '        }';
            } else {
                $lines[] = '        $result[\'' . $writeKey . '\'] = ' . $cast . ';';
            }
        }

        $lines[] = '';
        $lines[] = '        return $result;';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function generateCast(PhpMappersContext $ctx, Type $type, string $access, Definition $definition, string $direction): string
    {
        if ($type->isNullable() || $type->isArray()) {
            $innerType = $type->getInnerType();
            if ($innerType === null) {
                return $access;
            }
            if ($type->isNullable()) {
                $inner = $this->generateCast($ctx, $innerType, $access, $definition, $direction);
                return "({$access} !== null ? {$inner} : null)";
            }
            $elementCast = $this->generateCast($ctx, $innerType, '$v', $definition, $direction);
            return "array_map(fn(\$v) => {$elementCast}, {$access})";
        }

        $name = $type->getName();
        if ($name === null) {
            return $access;
        }

        if ($type->isReference()) {
            $struct = $definition->getStruct($name);
            if ($struct !== null && $struct->isInline()) {
                if (isset($ctx->pubStructToMapper[$name])) {
                    $mapperName = $ctx->pubStructToMapper[$name];
                    return $mapperName . "Mapper::{$direction}({$access})";
                }
                return $this->generateInlineCast($ctx, $struct, $access, $definition, $direction);
            }
            $phpName = str_replace('/', '', $name);
            return $phpName . "Mapper::{$direction}({$access})";
        }

        if ($type->isUnion()) {
            return $access;
        }

        if ($type->isStringLiteral()) {
            return "(string) {$access}";
        }

        if ($type->isAlias()) {
            $alias = $definition->getTypeAlias($name);
            $phpType = $alias?->getLangType('php');
            if ($phpType !== null) {
                return $this->castExpression($phpType, $access);
            }
            $resolvedType = $definition->getTypeAliasResolvedType($name);
            if ($resolvedType !== null) {
                return $this->generateCast($ctx, $resolvedType, $access, $definition, $direction);
            }
            return $access;
        }

        if ($type->isSimple()) {
            $alias = $definition->getTypeAlias($name);
            $phpType = $alias?->getLangType('php');
            if ($phpType === null) {
                throw new GeneratorException(sprintf('No PHP type mapping found for type "%s"', $name));
            }
            return $this->castExpression($phpType, $access);
        }

        return $access;
    }

    private function generateInlineCast(PhpMappersContext $ctx, Struct $struct, string $access, Definition $definition, string $direction): string
    {
        $entries = [];
        foreach ($struct->getProperties() as $prop) {
            $fromKey = MappingStrategyResolver::resolve($definition,$ctx->mapFrom ?? 'self', $prop->getName(), $prop->getAnnotations());
            $toKey = MappingStrategyResolver::resolve($definition,$ctx->mapTo ?? 'self', $prop->getName(), $prop->getAnnotations());
            $readKey = ($direction === 'fromArray') ? $toKey : $fromKey;
            $writeKey = ($direction === 'fromArray') ? $fromKey : $toKey;

            $fieldAccess = $access . "['{$readKey}']";
            $cast = $this->generateCast($ctx, $prop->getType(), $fieldAccess, $definition, $direction);
            $entries[] = "'{$writeKey}' => {$cast}";
        }
        return '[' . implode(', ', $entries) . ']';
    }

    private function castExpression(string $typeName, string $access): string
    {
        return match ($typeName) {
            'int' => "(int) {$access}",
            'float' => "(float) {$access}",
            'string' => "(string) {$access}",
            'bool' => "(bool) {$access}",
            default => $access,
        };
    }

}
