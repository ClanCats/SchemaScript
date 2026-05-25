<?php

namespace ClanCats\SchemaScript\Generator;

class GeneratorRegistry
{
    /**
     * @var array<string, GeneratorInterface>
     */
    private array $generators = [];

    public function register(GeneratorInterface $generator): void
    {
        $this->generators[$generator->getName()] = $generator;
    }

    public function get(string $name): GeneratorInterface
    {
        if (!isset($this->generators[$name])) {
            throw new \InvalidArgumentException("Unknown generator: '{$name}'. Available: " . implode(', ', $this->getNames()));
        }

        return $this->generators[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->generators[$name]);
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->generators);
    }

    /**
     * @return array<string, GeneratorInterface>
     */
    public function getAll(): array
    {
        return $this->generators;
    }
}
