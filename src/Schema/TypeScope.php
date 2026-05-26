<?php

namespace ClanCats\SchemaScript\Schema;

class TypeScope
{
    /**
     * @var array<string, TypeScopeEntry>
     */
    private array $typeAliases = [];

    /**
     * @var array<string, true>
     */
    private array $modelNames = [];

    private ?TypeScope $parent;

    public function __construct(?TypeScope $parent = null)
    {
        $this->parent = $parent;
    }

    public function registerType(string $name, bool $hasTypeDefinition): void
    {
        $this->typeAliases[$name] = new TypeScopeEntry($name, $hasTypeDefinition);
    }

    public function registerModelName(string $name): void
    {
        $this->modelNames[$name] = true;
    }

    public function resolveType(string $name): ?TypeScopeEntry
    {
        if (isset($this->typeAliases[$name])) {
            return $this->typeAliases[$name];
        }
        if ($this->parent !== null) {
            return $this->parent->resolveType($name);
        }
        return null;
    }

    public function isModelName(string $name): bool
    {
        if (isset($this->modelNames[$name])) {
            return true;
        }
        if ($this->parent !== null) {
            return $this->parent->isModelName($name);
        }
        return false;
    }

    public function hasName(string $name): bool
    {
        return $this->resolveType($name) !== null || $this->isModelName($name);
    }

    /**
     * @return array<string, TypeScopeEntry>
     */
    public function getLocalTypeAliases(): array
    {
        return $this->typeAliases;
    }

    /**
     * @return array<string, true>
     */
    public function getLocalModelNames(): array
    {
        return $this->modelNames;
    }
}
