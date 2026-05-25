<?php

namespace ClanCats\SchemaScript\Importer;

class ImporterRegistry
{
    /**
     * @var array<string, ImporterInterface>
     */
    private array $importers = [];

    public function register(ImporterInterface $importer): void
    {
        $this->importers[$importer->getName()] = $importer;
    }

    public function get(string $name): ImporterInterface
    {
        if (!isset($this->importers[$name])) {
            throw new \InvalidArgumentException("Unknown importer: '{$name}'. Available: " . implode(', ', $this->getNames()));
        }

        return $this->importers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->importers[$name]);
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->importers);
    }

    /**
     * @return array<string, ImporterInterface>
     */
    public function getAll(): array
    {
        return $this->importers;
    }
}
