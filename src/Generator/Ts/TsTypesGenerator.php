<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\MappingStrategyResolver;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeAlias;
use ClanCats\SchemaScript\Workbench\Str;

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
        $includeComments = (bool) ($options['include_comments'] ?? false);
        $result = new GeneratorResult();

        $ctx = new TsTypesContext();
        $ctx->mapName = $options['map'] ?? null;

        $pubAliases = $definition->getPublicTypeAliases();
        foreach ($pubAliases as $aliasName => $aliasData) {
            $resolved = $aliasData->getResolvedType();
            if ($resolved instanceof Type && $resolved->isReference()) {
                $structName = $resolved->getName();
                if ($structName !== null) {
                    $ctx->pubStructNames[$structName] = true;
                    $ctx->pubStructToAlias[$structName] = $aliasName;
                }
            }
        }

        if (!empty($pubAliases)) {
            $typesCode = $this->generateSharedTypes($ctx, $pubAliases, $definition, $includeComments);
            $result->addFile('_types.ts', $typesCode);
        }

        foreach ($definition->getModels() as $struct) {
            $ctx->resetReferencedPubTypes();
            $interfaceCode = $this->generateInterface($ctx, $struct, $definition, $includeComments);

            $lines = [];
            if (!empty($ctx->referencedPubTypes)) {
                /** @var array<string> $imports */
                $imports = array_keys($ctx->referencedPubTypes);
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
    private function generateSharedTypes(TsTypesContext $ctx, array $pubAliases, Definition $definition, bool $includeComments): string
    {
        $lines = [];

        foreach ($pubAliases as $aliasName => $aliasData) {
            $tsName = Str::toPascalCase($aliasName);
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
                        $lines[] = $this->generateProperty($ctx, $prop, $definition, '  ', $includeComments);
                    }
                    $lines[] = '}';
                    continue;
                }
            }

            if ($resolved instanceof Type) {
                if (!empty($lines)) {
                    $lines[] = '';
                }
                $resolver = $this->createTypeResolver($ctx, $definition);
                $tsType = $resolved->accept($resolver);
                $lines[] = 'export type ' . $tsName . ' = ' . $tsType . ';';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function generateInterface(TsTypesContext $ctx, Struct $struct, Definition $definition, bool $includeComments): string
    {
        $lines = [];
        $lines[] = 'export interface ' . $struct->getName() . ' {';

        foreach ($struct->getProperties() as $prop) {
            try {
                $lines[] = $this->generateProperty($ctx, $prop, $definition, '  ', $includeComments);
            } catch (GeneratorException $e) {
                throw (new GeneratorException($e->getMessage(), 0, $e))
                    ->setStructContext($struct->getName(), $prop->getName());
            }
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateProperty(TsTypesContext $ctx, StructProperty $prop, Definition $definition, string $indent, bool $includeComments = false): string
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

        $name = $ctx->mapName !== null
            ? MappingStrategyResolver::resolve($definition,$ctx->mapName, $prop->getName(), $prop->getAnnotations())
            : $prop->getName();
        $optional = $prop->isOptional() ? '?' : '';
        $resolver = $this->createTypeResolver($ctx, $definition, $indent);
        $type = $prop->getType()->accept($resolver);
        $lines[] = "{$indent}{$name}{$optional}: {$type};";

        return implode("\n", $lines);
    }

    private function createTypeResolver(TsTypesContext $ctx, Definition $definition, string $indent = ''): TsTypeResolver
    {
        return new TsTypeResolver(
            $ctx,
            $definition,
            fn(Struct $struct) => $this->generateInlineObject($ctx, $struct, $definition, $indent),
        );
    }

    private function generateInlineObject(TsTypesContext $ctx, Struct $struct, Definition $definition, string $indent): string
    {
        $innerIndent = $indent . '  ';
        $lines = ['{'];

        foreach ($struct->getProperties() as $prop) {
            $lines[] = $this->generateProperty($ctx, $prop, $definition, $innerIndent);
        }

        $lines[] = $indent . '}';

        return implode("\n", $lines);
    }

}
