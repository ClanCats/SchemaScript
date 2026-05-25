<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Util\StringHelper;

class PhpMappersGenerator implements GeneratorInterface
{
    /**
     * @var array<string, string>
     */
    private array $pubStructToMapper = [];

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

        $this->pubStructToMapper = [];
        foreach ($definition->getPublicTypeAliases() as $aliasName => $aliasData) {
            $resolved = $aliasData->getResolvedType();
            if ($resolved instanceof Type && $resolved->isReference()) {
                $structName = $resolved->getName();
                if ($structName !== null) {
                    $mapperName = StringHelper::toPascalCase($aliasName);
                    $this->pubStructToMapper[$structName] = $mapperName;
                }
            }
        }

        foreach ($this->pubStructToMapper as $structName => $mapperName) {
            $struct = $definition->getStruct($structName);
            if ($struct !== null) {
                $code = $this->generateMapper($struct, $definition, $namespace, $includeComments, $mapperName);
                $result->addFile($mapperName . 'Mapper.php', $code);
            }
        }

        foreach ($definition->getModels() as $struct) {
            $code = $this->generateMapper($struct, $definition, $namespace, $includeComments);
            $result->addFile($struct->getName() . 'Mapper.php', $code);
        }

        return $result;
    }

    private function generateMapper(Struct $struct, Definition $definition, ?string $namespace, bool $includeComments, ?string $nameOverride = null): string
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
        $lines[] = $this->generateMethod('fromArray', $struct, $definition, $includeComments);
        $lines[] = '';
        $lines[] = $this->generateMethod('toArray', $struct, $definition, $includeComments);
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateMethod(string $direction, Struct $struct, Definition $definition, bool $includeComments): string
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
            $key = $prop->getName();
            $varAccess = "\$data['{$key}']";
            $cast = $this->generateCast($prop->getType(), $varAccess, $definition, $direction);

            if ($prop->isOptional()) {
                $lines[] = "        if (array_key_exists('{$key}', \$data)) {";
                $lines[] = '            $result[\'' . $key . '\'] = ' . $cast . ';';
                $lines[] = '        }';
            } else {
                $lines[] = '        $result[\'' . $key . '\'] = ' . $cast . ';';
            }
        }

        $lines[] = '';
        $lines[] = '        return $result;';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function generateCast(Type $type, string $access, Definition $definition, string $direction): string
    {
        if ($type->isNullable() || $type->isArray()) {
            $innerType = $type->getInnerType();
            if ($innerType === null) {
                return $access;
            }
            if ($type->isNullable()) {
                $inner = $this->generateCast($innerType, $access, $definition, $direction);
                return "({$access} !== null ? {$inner} : null)";
            }
            $elementCast = $this->generateCast($innerType, '$v', $definition, $direction);
            return "array_map(fn(\$v) => {$elementCast}, {$access})";
        }

        $name = $type->getName();
        if ($name === null) {
            return $access;
        }

        if ($type->isReference()) {
            $struct = $definition->getStruct($name);
            if ($struct !== null && $struct->isInline()) {
                if (isset($this->pubStructToMapper[$name])) {
                    $mapperName = $this->pubStructToMapper[$name];
                    return $mapperName . "Mapper::{$direction}({$access})";
                }
                return $this->generateInlineCast($struct, $access, $definition, $direction);
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
                return $this->generateCast($resolvedType, $access, $definition, $direction);
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

    private function generateInlineCast(Struct $struct, string $access, Definition $definition, string $direction): string
    {
        $entries = [];
        foreach ($struct->getProperties() as $prop) {
            $key = $prop->getName();
            $fieldAccess = $access . "['{$key}']";
            $cast = $this->generateCast($prop->getType(), $fieldAccess, $definition, $direction);
            $entries[] = "'{$key}' => {$cast}";
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
