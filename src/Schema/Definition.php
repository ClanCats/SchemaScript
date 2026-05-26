<?php

namespace ClanCats\SchemaScript\Schema;

class Definition
{
    /**
     * @var array<MetadataEntry>
     */
    protected array $metadata;

    /**
     * @var array<string, TypeAlias>
     */
    protected array $typeAliases;

    /**
     * @var array<string, NamespaceDefinition>
     */
    protected array $namespaces;

    /**
     * @var array<string, Struct>
     */
    protected array $structs;

    /**
     * @param array<MetadataEntry> $metadata
     * @param array<string, TypeAlias> $typeAliases
     * @param array<string, NamespaceDefinition> $namespaces
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
     * @return array<MetadataEntry>
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

    /**
     * Returns a map of struct name => alias name for all public type aliases
     * that resolve to a struct reference.
     *
     * @return array<string, string>
     */
    public function getPublicStructAliasMap(): array
    {
        $map = [];
        foreach ($this->getPublicTypeAliases() as $aliasName => $aliasData) {
            $resolved = $aliasData->getResolvedType();
            if ($resolved instanceof Type && $resolved->isReference()) {
                $structName = $resolved->getName();
                if ($structName !== null) {
                    $map[$structName] = $aliasName;
                }
            }
        }
        return $map;
    }

    public function getTypeAliasResolvedType(string $name): ?Type
    {
        $alias = $this->typeAliases[$name] ?? null;
        return $alias !== null ? $alias->getResolvedType() : null;
    }

    /**
     * @return array<string, NamespaceDefinition>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    public function getNamespace(string $name): ?NamespaceDefinition
    {
        return $this->namespaces[$name] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNamespaceConstants(string $name): ?array
    {
        $ns = $this->namespaces[$name] ?? null;
        return $ns !== null ? $ns->toArray() : null;
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
            if ($entry->getKey() === $key) {
                return $entry->getValue();
            }
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function resolveReference(string $namespace, string $constant): mixed
    {
        $ns = $this->namespaces[$namespace] ?? null;
        if ($ns === null || !$ns->hasConstant($constant)) {
            return null;
        }

        return $ns->getConstantValue($constant);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->metadata) {
            $data['metadata'] = array_map(fn(MetadataEntry $e) => $e->toArray(), $this->metadata);
        }

        if ($this->typeAliases) {
            $data['typeAliases'] = array_map(fn(TypeAlias $a) => $a->toArray(), $this->typeAliases);
        }

        if ($this->namespaces) {
            $data['namespaces'] = array_map(fn(NamespaceDefinition $ns) => $ns->toArray(), $this->namespaces);
        }

        if ($this->structs) {
            $data['structs'] = array_map(fn(Struct $s) => $s->toArray(), $this->structs);
        }

        return $data;
    }
}
