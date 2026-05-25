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
                $cast = $prop->getType()->accept($this->createCastVisitor($ctx, $definition, $direction, $varAccess));
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

    private function createCastVisitor(PhpMappersContext $ctx, Definition $definition, string $direction, string $access): PhpMappersCastVisitor
    {
        return new PhpMappersCastVisitor(
            $ctx,
            $definition,
            $direction,
            $access,
            fn(Struct $struct, string $inlineAccess) => $this->generateInlineCast($ctx, $struct, $inlineAccess, $definition, $direction),
        );
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
            $cast = $prop->getType()->accept($this->createCastVisitor($ctx, $definition, $direction, $fieldAccess));
            $entries[] = "'{$writeKey}' => {$cast}";
        }
        return '[' . implode(', ', $entries) . ']';
    }

}
