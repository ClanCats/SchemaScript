<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;

class TsTypesGenerator implements GeneratorInterface
{
    public function getName(): string
    {
        return 'ts.types';
    }

    public function getDescription(): string
    {
        return 'Generates TypeScript interface type definitions';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generate(Definition $definition, array $options = []): GeneratorResult
    {
        $result = new GeneratorResult();

        foreach ($definition->getModels() as $struct) {
            $code = $this->generateInterface($struct, $definition);
            $result->addFile($struct->getName() . '.ts', $code);
        }

        return $result;
    }

    private function generateInterface(Struct $struct, Definition $definition): string
    {
        $lines = [];
        $lines[] = 'export interface ' . $struct->getName() . ' {';

        foreach ($struct->getProperties() as $prop) {
            $lines[] = $this->generateProperty($prop, $definition, '  ');
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateProperty(StructProperty $prop, Definition $definition, string $indent): string
    {
        $name = $prop->getName();
        $optional = $prop->isOptional() ? '?' : '';
        $type = $this->resolveType($prop->getType(), $definition, $indent);

        return "{$indent}{$name}{$optional}: {$type};";
    }

    private function resolveType(Type $type, Definition $definition, string $indent): string
    {
        if ($type->isNullable()) {
            $inner = $type->getInnerType();
            if ($inner === null) {
                return 'null';
            }
            return $this->resolveType($inner, $definition, $indent) . ' | null';
        }

        if ($type->isArray()) {
            $inner = $type->getInnerType();
            if ($inner === null) {
                return 'unknown[]';
            }
            $elementType = $this->resolveType($inner, $definition, $indent);
            if ($inner->isUnion() || $inner->isNullable()) {
                return '(' . $elementType . ')[]';
            }
            return $elementType . '[]';
        }

        if ($type->isUnion()) {
            $parts = [];
            foreach ($type->getUnionTypes() as $unionType) {
                $parts[] = $this->resolveType($unionType, $definition, $indent);
            }
            return implode(' | ', $parts);
        }

        if ($type->isStringLiteral()) {
            return "'" . str_replace("'", "\\'", $type->getName() ?? '') . "'";
        }

        if ($type->isReference()) {
            $refName = $type->getName();
            if ($refName === null) {
                return 'unknown';
            }
            $struct = $definition->getStruct($refName);
            if ($struct !== null && $struct->isInline()) {
                return $this->generateInlineObject($struct, $definition, $indent);
            }
            return $refName;
        }

        if ($type->isAlias()) {
            $name = $type->getName();
            if ($name === null) {
                return 'unknown';
            }
            $alias = $definition->getTypeAlias($name);
            if ($alias !== null) {
                $tsType = $alias['annotations']['lang.ts'][0] ?? null;
                if ($tsType !== null) {
                    return $tsType;
                }
            }
            $resolved = $definition->getTypeAliasResolvedType($name);
            if ($resolved !== null) {
                return $this->resolveType($resolved, $definition, $indent);
            }
            return $name;
        }

        if ($type->isSimple()) {
            return $this->mapSimpleType($type->getName() ?? 'unknown');
        }

        return 'unknown';
    }

    private function generateInlineObject(Struct $struct, Definition $definition, string $indent): string
    {
        $innerIndent = $indent . '  ';
        $lines = ['{'];

        foreach ($struct->getProperties() as $prop) {
            $lines[] = $this->generateProperty($prop, $definition, $innerIndent);
        }

        $lines[] = $indent . '}';

        return implode("\n", $lines);
    }

    private function mapSimpleType(string $name): string
    {
        return match ($name) {
            'int', 'int8', 'int16', 'int32', 'int64',
            'uint', 'uint8', 'uint16', 'uint32', 'uint64',
            'float', 'float32', 'float64', 'double' => 'number',
            'string', 'uuid' => 'string',
            'bool' => 'boolean',
            'bytes' => 'Uint8Array',
            'timestamp', 'datetime', 'date', 'time' => 'string',
            'any', 'mixed' => 'unknown',
            default => $name,
        };
    }
}
