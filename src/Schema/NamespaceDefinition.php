<?php

namespace ClanCats\SchemaScript\Schema;

class NamespaceDefinition
{
    /**
     * @param array<string, NamespaceConstant> $constants
     */
    public function __construct(
        private string $name,
        private array $constants = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, NamespaceConstant>
     */
    public function getConstants(): array
    {
        return $this->constants;
    }

    public function hasConstant(string $name): bool
    {
        return isset($this->constants[$name]);
    }

    public function getConstant(string $name): ?NamespaceConstant
    {
        return $this->constants[$name] ?? null;
    }

    public function getConstantValue(string $name): mixed
    {
        return isset($this->constants[$name]) ? $this->constants[$name]->getValue() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->constants as $name => $constant) {
            $result[$name] = $constant->getValue();
        }
        return $result;
    }
}
