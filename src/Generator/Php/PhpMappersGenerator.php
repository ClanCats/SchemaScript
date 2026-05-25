<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\Type;

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
        $namespace = $options['namespace'] ?? null;

        foreach ($definition->getStructs() as $struct) {
            $code = $this->generateMapper($struct, $definition, $namespace);
            $result->addFile($struct->getName() . 'Mapper.php', $code);
        }

        return $result;
    }

    private function generateMapper(Struct $struct, Definition $definition, ?string $namespace = null): string
    {
        $name = str_replace('/', '', $struct->getName());
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        if ($namespace !== null) {
            $lines[] = 'namespace ' . rtrim($namespace, '\\') . ';';
            $lines[] = '';
        }
        $lines[] = "class {$name}Mapper";
        $lines[] = '{';
        $lines[] = $this->generateFromArray($struct, $definition);
        $lines[] = '';
        $lines[] = $this->generateToArray($struct, $definition);
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateFromArray(Struct $struct, Definition $definition): string
    {
        return $this->generateMethod('fromArray', $struct, $definition);
    }

    private function generateToArray(Struct $struct, Definition $definition): string
    {
        return $this->generateMethod('toArray', $struct, $definition);
    }

    private function generateMethod(string $direction, Struct $struct, Definition $definition): string
    {
        $lines = [];
        $lines[] = "    public static function {$direction}(array \$data): array";
        $lines[] = '    {';
        $lines[] = '        $result = [];';

        foreach ($struct->getProperties() as $prop) {
            $lines[] = '';
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
            $phpType = $alias['annotations']['lang.php'][0] ?? null;
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
            return $this->castExpression($name, $access);
        }

        return $access;
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
