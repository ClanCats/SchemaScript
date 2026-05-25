<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeAlias;
use ClanCats\SchemaScript\Util\StringHelper;

class TsTypesGenerator implements GeneratorInterface
{
    /**
     * @var array<string, true>
     */
    private array $pubStructNames = [];

    /**
     * @var array<string, string>
     */
    private array $pubStructToAlias = [];

    /**
     * @var array<string, true>
     */
    private array $referencedPubTypes = [];

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
        $includeComments = (bool) ($options['include_comments'] ?? false);
        $result = new GeneratorResult();

        $this->pubStructNames = [];
        $this->pubStructToAlias = [];

        $pubAliases = $definition->getPublicTypeAliases();
        foreach ($pubAliases as $aliasName => $aliasData) {
            $resolved = $aliasData->getResolvedType();
            if ($resolved instanceof Type && $resolved->isReference()) {
                $structName = $resolved->getName();
                if ($structName !== null) {
                    $this->pubStructNames[$structName] = true;
                    $this->pubStructToAlias[$structName] = $aliasName;
                }
            }
        }

        if (!empty($pubAliases)) {
            $typesCode = $this->generateSharedTypes($pubAliases, $definition, $includeComments);
            $result->addFile('_types.ts', $typesCode);
        }

        foreach ($definition->getModels() as $struct) {
            $this->referencedPubTypes = [];
            $interfaceCode = $this->generateInterface($struct, $definition, $includeComments);

            $lines = [];
            if (!empty($this->referencedPubTypes)) {
                /** @var array<string> $imports */
                $imports = array_keys($this->referencedPubTypes);
                sort($imports);
                $lines[] = "import type { " . implode(', ', $imports) . " } from './_types';";
                $lines[] = '';
            }
            $lines[] = $interfaceCode;

            $result->addFile($struct->getName() . '.ts', implode("\n", $lines));
        }

        return $result;
    }

    /**
     * @param array<string, TypeAlias> $pubAliases
     */
    private function generateSharedTypes(array $pubAliases, Definition $definition, bool $includeComments): string
    {
        $lines = [];

        foreach ($pubAliases as $aliasName => $aliasData) {
            $tsName = StringHelper::toPascalCase($aliasName);
            $resolved = $aliasData->getResolvedType();

            if ($resolved instanceof Type && $resolved->isReference()) {
                $structName = $resolved->getName();
                $struct = $structName !== null ? $definition->getStruct($structName) : null;
                if ($struct !== null) {
                    if (!empty($lines)) {
                        $lines[] = '';
                    }
                    $lines[] = 'export interface ' . $tsName . ' {';
                    foreach ($struct->getProperties() as $prop) {
                        $lines[] = $this->generateProperty($prop, $definition, '  ', $includeComments);
                    }
                    $lines[] = '}';
                    continue;
                }
            }

            if ($resolved instanceof Type) {
                if (!empty($lines)) {
                    $lines[] = '';
                }
                $tsType = $this->resolveType($resolved, $definition, '');
                $lines[] = 'export type ' . $tsName . ' = ' . $tsType . ';';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function generateInterface(Struct $struct, Definition $definition, bool $includeComments): string
    {
        $lines = [];
        $lines[] = 'export interface ' . $struct->getName() . ' {';

        foreach ($struct->getProperties() as $prop) {
            $lines[] = $this->generateProperty($prop, $definition, '  ', $includeComments);
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateProperty(StructProperty $prop, Definition $definition, string $indent, bool $includeComments = false): string
    {
        $lines = [];

        if ($includeComments && $prop->getComment() !== null) {
            $commentLines = explode("\n", $prop->getComment());
            if (count($commentLines) === 1) {
                $lines[] = "{$indent}/** {$commentLines[0]} */";
            } else {
                $lines[] = "{$indent}/**";
                foreach ($commentLines as $cl) {
                    $lines[] = "{$indent} * {$cl}";
                }
                $lines[] = "{$indent} */";
            }
        }

        $name = $prop->getName();
        $optional = $prop->isOptional() ? '?' : '';
        $type = $this->resolveType($prop->getType(), $definition, $indent);
        $lines[] = "{$indent}{$name}{$optional}: {$type};";

        return implode("\n", $lines);
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
            if (isset($this->pubStructNames[$refName])) {
                $aliasName = $this->pubStructToAlias[$refName];
                $tsName = StringHelper::toPascalCase($aliasName);
                $this->referencedPubTypes[$tsName] = true;
                return $tsName;
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
            if ($definition->isTypeAliasPublic($name)) {
                $tsName = StringHelper::toPascalCase($name);
                $this->referencedPubTypes[$tsName] = true;
                return $tsName;
            }
            $alias = $definition->getTypeAlias($name);
            $tsType = $alias?->getLangType('ts');
            if ($tsType !== null) {
                return $tsType;
            }
            $resolved = $definition->getTypeAliasResolvedType($name);
            if ($resolved !== null) {
                return $this->resolveType($resolved, $definition, $indent);
            }
            return $name;
        }

        if ($type->isSimple()) {
            $name = $type->getName();
            if ($name === null) {
                throw new GeneratorException('Encountered a simple type without a name');
            }
            $alias = $definition->getTypeAlias($name);
            $tsType = $alias?->getLangType('ts');
            if ($tsType === null) {
                throw new GeneratorException(sprintf('No TypeScript type mapping found for type "%s"', $name));
            }
            return $tsType;
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

}
