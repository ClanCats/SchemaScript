<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Schema\ResolvedDefinition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeVisitorInterface;
use ClanCats\SchemaScript\Workbench\Str;
use Closure;

/**
 * @implements TypeVisitorInterface<string>
 */
class TsTypeResolver implements TypeVisitorInterface
{
    /**
     * @param Closure(Struct): string $inlineObjectRenderer
     */
    public function __construct(
        private TsTypesContext $ctx,
        private ResolvedDefinition $resolved,
        private Closure $inlineObjectRenderer,
    ) {}

    public function visitNullable(Type $innerType): string
    {
        return $innerType->accept($this) . ' | null';
    }

    public function visitArray(Type $elementType): string
    {
        $resolved = $elementType->accept($this);
        if ($elementType->isUnion() || $elementType->isNullable()) {
            return '(' . $resolved . ')[]';
        }
        return $resolved . '[]';
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): string
    {
        $parts = array_map(fn(Type $t) => $t->accept($this), $types);
        return implode(' | ', $parts);
    }

    public function visitSimple(string $name): string
    {
        $info = $this->resolved->getTypeInfo($name);
        $tsType = $info?->getLangType('ts');
        if ($tsType === null) {
            throw new GeneratorException(sprintf('No TypeScript type mapping found for type "%s"', $name));
        }
        return $tsType;
    }

    public function visitReference(string $name): string
    {
        if (isset($this->ctx->pubStructNames[$name])) {
            $aliasName = $this->ctx->pubStructToAlias[$name];
            $tsName = Str::toPascalCase($aliasName);
            $this->ctx->referencedPubTypes[$tsName] = true;
            return $tsName;
        }
        $struct = $this->resolved->getStruct($name);
        if ($struct !== null && $struct->isInline()) {
            return ($this->inlineObjectRenderer)($struct);
        }
        $this->ctx->referencedStructs[$name] = true;
        return $name;
    }

    public function visitAlias(string $name): string
    {
        if ($this->resolved->isTypeAliasPublic($name)) {
            $tsName = Str::toPascalCase($name);
            $this->ctx->referencedPubTypes[$tsName] = true;
            return $tsName;
        }
        $info = $this->resolved->getTypeInfo($name);
        if ($info !== null && $info->getLangType('ts') !== null) {
            return $info->getLangType('ts');
        }
        if ($info !== null && $info->getResolvedType() !== null) {
            return $info->getResolvedType()->accept($this);
        }
        return $name;
    }

    public function visitStringLiteral(string $value): string
    {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }

    public function visitTypeParameter(string $name): string
    {
        return $name;
    }

    /**
     * @param array<Type> $typeArguments
     */
    public function visitGeneric(string $baseName, array $typeArguments): string
    {
        $args = array_map(fn(Type $t) => $t->accept($this), $typeArguments);

        $struct = $this->resolved->getStruct($baseName);
        if ($struct !== null) {
            $langAnnotation = $struct->getAnnotation('lang.ts');
            if ($langAnnotation !== null) {
                $tsName = $langAnnotation->getArguments()[0] ?? $baseName;
                return $tsName . '<' . implode(', ', $args) . '>';
            }
        }

        return $baseName . '<' . implode(', ', $args) . '>';
    }
}
