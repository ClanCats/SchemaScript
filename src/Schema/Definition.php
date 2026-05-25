<?php

namespace ClanCats\SchemaScript\Schema;

class Definition
{
    /**
     * @var array<array{key: string, value: mixed, attributes: AnnotationCollection}>
     */
    protected array $metadata;

    /**
     * @var array<string, TypeAlias>
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
     * @param array<array{key: string, value: mixed, attributes: AnnotationCollection}> $metadata
     * @param array<string, TypeAlias> $typeAliases
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
     * @return array<array{key: string, value: mixed, attributes: AnnotationCollection}>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, TypeAlias>
     */
    public function getTypeAliases(): array
    {
        return $this->typeAliases;
    }

    public function getTypeAlias(string $name): ?TypeAlias
    {
        return $this->typeAliases[$name] ?? null;
    }

    public function isTypeAliasPublic(string $name): bool
    {
        $alias = $this->typeAliases[$name] ?? null;
        return $alias !== null && $alias->isPublic();
    }

    /**
     * @return array<string, TypeAlias>
     */
    public function getPublicTypeAliases(): array
    {
        return array_filter($this->typeAliases, fn(TypeAlias $a) => $a->isPublic());
    }

    public function getTypeAliasResolvedType(string $name): ?Type
    {
        $alias = $this->typeAliases[$name] ?? null;
        return $alias !== null ? $alias->getResolvedType() : null;
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->metadata) {
            $data['metadata'] = array_map(function (array $entry) {
                $entry['attributes'] = $entry['attributes']->toArray();
                return $entry;
            }, $this->metadata);
        }

        if ($this->typeAliases) {
            $data['typeAliases'] = array_map(fn(TypeAlias $a) => $a->toArray(), $this->typeAliases);
        }

        if ($this->namespaces) {
            $data['namespaces'] = $this->namespaces;
        }

        if ($this->structs) {
            $data['structs'] = array_map(fn(Struct $s) => $s->toArray(), $this->structs);
        }

        return $data;
    }
}
