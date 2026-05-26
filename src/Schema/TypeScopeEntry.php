<?php

namespace ClanCats\SchemaScript\Schema;

class TypeScopeEntry
{
    public function __construct(
        private string $name,
        private bool $hasTypeDefinition,
        private bool $isTypeParameter = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function hasTypeDefinition(): bool
    {
        return $this->hasTypeDefinition;
    }

    public function isTypeParameter(): bool
    {
        return $this->isTypeParameter;
    }
}
