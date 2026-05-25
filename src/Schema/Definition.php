<?php

namespace ClanCats\SchemaScript\Schema;

class Definition
{
    /**
     * @var array<array{key: string, value: mixed, attributes: array<string, mixed[]>}>
     */
    protected array $metadata;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $typeAliases;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $namespaces;

    /**
     * @var array<string, Struct>
     */
    protected array $structs;

    /**
     * @param array<array{key: string, value: mixed, attributes: array<string, mixed[]>}> $metadata
     * @param array<string, array<string, mixed>> $typeAliases
     * @param array<string, array<string, mixed>> $namespaces
     * @param array<string, Struct> $structs
     */
    public function __construct(array $metadata = [], array $typeAliases = [], array $namespaces = [], array $structs = [])
    {
        $this->metadata = $metadata;
        $this->typeAliases = $typeAliases;
        $this->namespaces = $namespaces;
        $this->structs = $structs;
    }

    /**
     * @return array<array{key: string, value: mixed, attributes: array<string, mixed[]>}>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTypeAliases(): array
    {
        return $this->typeAliases;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTypeAlias(string $name): ?array
    {
        return $this->typeAliases[$name] ?? null;
    }

    public function getTypeAliasResolvedType(string $name): ?Type
    {
        $alias = $this->typeAliases[$name] ?? null;
        if ($alias === null) {
            return null;
        }
        return $alias['resolvedType'] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNamespaceConstants(string $name): ?array
    {
        return $this->namespaces[$name] ?? null;
    }

    /**
     * @return array<string, Struct>
     */
    public function getStructs(): array
    {
        return $this->structs;
    }

    public function getStruct(string $name): ?Struct
    {
        return $this->structs[$name] ?? null;
    }

    /**
     * @return array<string, Struct>
     */
    public function getModels(): array
    {
        return array_filter($this->structs, fn(Struct $s) => !$s->isInline());
    }

    /**
     * @return array<string, Struct>
     */
    public function getInlineStructs(): array
    {
        return array_filter($this->structs, fn(Struct $s) => $s->isInline());
    }

    /**
     * @return mixed
     */
    public function findMetadataValue(string $key)
    {
        foreach ($this->metadata as $entry) {
            if ($entry['key'] === $key) {
                return $entry['value'];
            }
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function resolveReference(string $namespace, string $constant)
    {
        if (!isset($this->namespaces[$namespace])) {
            return null;
        }

        if (!isset($this->namespaces[$namespace][$constant])) {
            return null;
        }

        return $this->namespaces[$namespace][$constant];
    }
}
