<?php

namespace ClanCats\SchemaScript\Schema;

class NamespaceConstant
{
    public function __construct(
        private string $name,
        private mixed $value,
        private bool $hasExplicitValue,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function hasExplicitValue(): bool
    {
        return $this->hasExplicitValue;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'hasExplicitValue' => $this->hasExplicitValue,
        ];
    }
}
