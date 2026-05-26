<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Schema\ResolvedDefinition;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeVisitorInterface;

/**
 * @implements TypeVisitorInterface<string>
 */
abstract class PhpBaseCastVisitor implements TypeVisitorInterface
{
    public function __construct(
        protected ResolvedDefinition $resolved,
        protected string $direction,
        protected string $access,
    ) {}

    abstract protected function createWithAccess(string $access): PhpBaseCastVisitor;

    abstract protected function castPrimitive(string $typeName, string $access): string;

    protected function fallbackAccess(): string
    {
        return $this->access;
    }

    public function visitArray(Type $elementType): string
    {
        $elementCast = $elementType->accept($this->createWithAccess('$v'));
        return "array_map(fn(\$v) => {$elementCast}, {$this->access})";
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): string
    {
        return $this->access;
    }

    public function visitSimple(string $name): string
    {
        return $this->castPrimitive($name, $this->access);
    }

    public function visitAlias(string $name): string
    {
        $info = $this->resolved->getTypeInfo($name);
        if ($info !== null && $info->getLangType('php') !== null) {
            return $this->castPrimitive($info->getLangType('php'), $this->access);
        }
        if ($info !== null && $info->getResolvedType() !== null) {
            return $info->getResolvedType()->accept($this);
        }
        return $this->fallbackAccess();
    }

    public function visitStringLiteral(string $value): string
    {
        return $this->castPrimitive('string', $this->access);
    }
}
