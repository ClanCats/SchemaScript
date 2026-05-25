<?php

namespace ClanCats\SchemaScript\Schema;

/**
 * @template T
 */
interface TypeVisitorInterface
{
    /**
     * @return T
     */
    public function visitNullable(Type $innerType): mixed;

    /**
     * @return T
     */
    public function visitArray(Type $elementType): mixed;

    /**
     * @param array<Type> $types
     * @return T
     */
    public function visitUnion(array $types): mixed;

    /**
     * @return T
     */
    public function visitSimple(string $name): mixed;

    /**
     * @return T
     */
    public function visitReference(string $name): mixed;

    /**
     * @return T
     */
    public function visitAlias(string $name): mixed;

    /**
     * @return T
     */
    public function visitStringLiteral(string $value): mixed;
}
