<?php

namespace ClanCats\SchemaScript\Schema;

/**
 * @implements TypeVisitorInterface<null>
 */
class TypeCollectorVisitor implements TypeVisitorInterface
{
    /** @var array<string, true> */
    private array $simpleTypes = [];

    /** @var array<string, true> */
    private array $references = [];

    /** @var array<string, true> */
    private array $aliases = [];

    /** @var array<string, true> */
    private array $stringLiterals = [];

    /** @var array<string, int> */
    private array $genericUsages = [];

    public function visitNullable(Type $innerType): mixed
    {
        $innerType->accept($this);
        return null;
    }

    public function visitArray(Type $elementType): mixed
    {
        $elementType->accept($this);
        return null;
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): mixed
    {
        foreach ($types as $type) {
            $type->accept($this);
        }
        return null;
    }

    public function visitSimple(string $name): mixed
    {
        $this->simpleTypes[$name] = true;
        return null;
    }

    public function visitReference(string $name): mixed
    {
        $this->references[$name] = true;
        return null;
    }

    public function visitAlias(string $name): mixed
    {
        $this->aliases[$name] = true;
        return null;
    }

    public function visitStringLiteral(string $value): mixed
    {
        $this->stringLiterals[$value] = true;
        return null;
    }

    public function visitTypeParameter(string $name): mixed
    {
        return null;
    }

    /**
     * @param array<Type> $typeArguments
     */
    public function visitGeneric(string $baseName, array $typeArguments): mixed
    {
        $this->references[$baseName] = true;
        $this->genericUsages[$baseName] = count($typeArguments);
        foreach ($typeArguments as $arg) {
            $arg->accept($this);
        }
        return null;
    }

    /** @return list<string> */
    public function getSimpleTypes(): array
    {
        return array_keys($this->simpleTypes);
    }

    /** @return list<string> */
    public function getReferences(): array
    {
        return array_keys($this->references);
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return array_keys($this->aliases);
    }

    /** @return list<string> */
    public function getStringLiterals(): array
    {
        return array_keys($this->stringLiterals);
    }

    /** @return array<string, int> base name => argument count */
    public function getGenericUsages(): array
    {
        return $this->genericUsages;
    }

    public function reset(): void
    {
        $this->simpleTypes = [];
        $this->references = [];
        $this->aliases = [];
        $this->stringLiterals = [];
        $this->genericUsages = [];
    }
}
