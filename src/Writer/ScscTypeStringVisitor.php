<?php

namespace ClanCats\SchemaScript\Writer;

use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeVisitorInterface;

/**
 * @implements TypeVisitorInterface<string>
 */
class ScscTypeStringVisitor implements TypeVisitorInterface
{
    public function visitNullable(Type $innerType): string
    {
        return $innerType->accept($this) . '?';
    }

    public function visitArray(Type $elementType): string
    {
        return $elementType->accept($this) . '[]';
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): string
    {
        return implode('|', array_map(fn(Type $t) => $t->accept($this), $types));
    }

    public function visitSimple(string $name): string
    {
        return $name;
    }

    public function visitReference(string $name): string
    {
        return $name;
    }

    public function visitAlias(string $name): string
    {
        return $name;
    }

    public function visitStringLiteral(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }
}
