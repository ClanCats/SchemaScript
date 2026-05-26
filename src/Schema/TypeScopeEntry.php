<?php

namespace ClanCats\SchemaScript\Schema;

class TypeScopeEntry
{
    public function __construct(
        private string $name,
        private bool $hasTypeDefinition,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function hasTypeDefinition(): bool
    {
        return $this->hasTypeDefinition;
    }
}
