<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Generator\GeneratorInterface;
use ClanCats\SchemaScript\Generator\GeneratorResult;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\ResolvedDefinition;
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

        $mapNames = $ctx->mapName !== null ? [$ctx->mapName] : [];
        $resolved = new ResolvedDefinition($definition, $mapNames);

        foreach ($resolved->getPublicStructAliasMap() as $structName => $aliasName) {
            $ctx->pubStructNames[$structName] = true;
            $ctx->pubStructToAlias[$structName] = $aliasName;
        }

        $pubAliases = $resolved->getPublicTypeAliases();
        if (!empty($pubAliases)) {
            $typesCode = $this->generateSharedTypes($ctx, $pubAliases, $resolved, $includeComments);
            $result->addFile('_types.ts', $typesCode);
        }

        foreach ($resolved->getModels() as $struct) {
            if ($struct->isGeneric() && $struct->hasAnnotation('lang.ts')) {
                continue;
            }
            $ctx->resetReferencedPubTypes();
            $interfaceCode = $this->generateInterface($ctx, $struct, $resolved, $includeComments);

            $lines = [];
            if (!empty($ctx->referencedPubTypes)) {
                /** @var array<string> $imports */
                $imports = array_keys($ctx->referencedPubTypes);
                sort($imports);
                $lines[] = "import type { " . implode(', ', $imports) . " } from './_types';";
            }

            $structImports = array_keys($ctx->referencedStructs);
            $selfIdx = array_search($struct->getName(), $structImports, true);
            if ($selfIdx !== false) {
                unset($structImports[$selfIdx]);
            }
            sort($structImports);
            foreach ($structImports as $refName) {
                $lines[] = "import type { " . $refName . " } from './" . $refName . "';";
            }

            if (!empty($lines)) {
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
    private function generateSharedTypes(TsTypesContext $ctx, array $pubAliases, ResolvedDefinition $resolved, bool $includeComments): string
    {
        $lines = [];

        foreach ($pubAliases as $aliasName => $aliasData) {
            $tsName = Str::toPascalCase($aliasName);
            $resolvedType = $aliasData->getResolvedType();

            if ($resolvedType instanceof Type && $resolvedType->isReference()) {
                $structName = $resolvedType->getName();
                $struct = $structName !== null ? $resolved->getStruct($structName) : null;
                if ($struct !== null) {
                    if (!empty($lines)) {
                        $lines[] = '';
                    }
                    $lines[] = 'export interface ' . $tsName . ' {';
                    $ctx->currentStructName = $struct->getName();
                    foreach ($struct->getProperties() as $prop) {
                        $lines[] = $this->generateProperty($ctx, $prop, $resolved, '  ', $includeComments);
                    }
                    $lines[] = '}';
                    continue;
                }
            }

            if ($resolvedType instanceof Type) {
                if (!empty($lines)) {
                    $lines[] = '';
                }
                $resolver = $this->createTypeResolver($ctx, $resolved);
                $tsType = $resolvedType->accept($resolver);
                $lines[] = 'export type ' . $tsName . ' = ' . $tsType . ';';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function generateInterface(TsTypesContext $ctx, Struct $struct, ResolvedDefinition $resolved, bool $includeComments): string
    {
        $lines = [];
        $typeParamSuffix = '';
        if ($struct->isGeneric()) {
            $typeParamSuffix = '<' . implode(', ', $struct->getTypeParameters()) . '>';
        }
        $lines[] = 'export interface ' . $struct->getName() . $typeParamSuffix . ' {';
        $ctx->currentStructName = $struct->getName();

        foreach ($struct->getProperties() as $prop) {
            try {
                $lines[] = $this->generateProperty($ctx, $prop, $resolved, '  ', $includeComments);
            } catch (GeneratorException $e) {
                throw (new GeneratorException($e->getMessage(), 0, $e))
                    ->setStructContext($struct->getName(), $prop->getName());
            }
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function generateProperty(TsTypesContext $ctx, StructProperty $prop, ResolvedDefinition $resolved, string $indent, bool $includeComments = false): string
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
            ? $ctx->currentStructName !== null
                ? $resolved->getResolvedKey($ctx->currentStructName, $prop->getName(), $ctx->mapName)
                : $prop->getName()
            : $prop->getName();
        $optional = $prop->isOptional() ? '?' : '';
        $resolver = $this->createTypeResolver($ctx, $resolved, $indent);
        $type = $prop->getType()->accept($resolver);
        $lines[] = "{$indent}{$name}{$optional}: {$type};";

        return implode("\n", $lines);
    }

    private function createTypeResolver(TsTypesContext $ctx, ResolvedDefinition $resolved, string $indent = ''): TsTypeResolver
    {
        return new TsTypeResolver(
            $ctx,
            $resolved,
            fn(Struct $struct) => $this->generateInlineObject($ctx, $struct, $resolved, $indent),
        );
    }

    private function generateInlineObject(TsTypesContext $ctx, Struct $struct, ResolvedDefinition $resolved, string $indent): string
    {
        $innerIndent = $indent . '  ';
        $lines = ['{'];
        $ctx->currentStructName = $struct->getName();

        foreach ($struct->getProperties() as $prop) {
            $lines[] = $this->generateProperty($ctx, $prop, $resolved, $innerIndent);
        }

        $lines[] = $indent . '}';

        return implode("\n", $lines);
    }

}
