<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Schema\Definition;
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
        private Definition $definition,
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
        $alias = $this->definition->getTypeAlias($name);
        $tsType = $alias?->getLangType('ts');
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
        $struct = $this->definition->getStruct($name);
        if ($struct !== null && $struct->isInline()) {
            return ($this->inlineObjectRenderer)($struct);
        }
        return $name;
    }

    public function visitAlias(string $name): string
    {
        if ($this->definition->isTypeAliasPublic($name)) {
            $tsName = Str::toPascalCase($name);
            $this->ctx->referencedPubTypes[$tsName] = true;
            return $tsName;
        }
        $alias = $this->definition->getTypeAlias($name);
        $tsType = $alias?->getLangType('ts');
        if ($tsType !== null) {
            return $tsType;
        }
        $resolved = $this->definition->getTypeAliasResolvedType($name);
        if ($resolved !== null) {
            return $resolved->accept($this);
        }
        return $name;
    }

    public function visitStringLiteral(string $value): string
    {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }
}
