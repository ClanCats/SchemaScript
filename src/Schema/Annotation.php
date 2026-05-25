<?php

namespace ClanCats\SchemaScript\Schema;

class Annotation
{
    /**
     * @param array<mixed> $arguments
     */
    public function __construct(
        private string $name,
        private array $arguments = []
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getFirstArgument(): mixed
    {
        return $this->arguments[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
