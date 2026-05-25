<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\TypeAliasNode;

class TypeScope
{
    /**
     * @var array<string, TypeAliasNode>
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

    public function registerTypeAlias(TypeAliasNode $alias): void
    {
        $this->typeAliases[$alias->getName()] = $alias;
    }

    public function registerModelName(string $name): void
    {
        $this->modelNames[$name] = true;
    }

    public function resolveType(string $name): ?TypeAliasNode
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
     * @return array<string, TypeAliasNode>
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
