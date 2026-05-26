<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\ConstantNode;

class EvaluationContext
{
    /**
     * @var array<string, Struct>
     */
    private array $structs = [];

    /**
     * @var array<string, TypeAlias>
     */
    private array $typeAliasData = [];

    /**
     * @var array<string, NamespaceDefinition>
     */
    private array $namespaces = [];

    /**
     * @var array<string, true>
     */
    private array $resolvingRefs = [];

    /**
     * @var array<string, string>
     */
    private array $identifierConstants = [];

    /**
     * @var array<string, ConstantNode>
     */
    private array $valueConstants = [];

    /**
     * @var array<string, string>
     */
    private array $implicitTypeAliases = [];

    /**
     * @var array<string, string>
     */
    private array $sourceCodeMap = [];

    // --- Structs ---

    public function registerStruct(string $name, Struct $struct): void
    {
        $this->structs[$name] = $struct;
    }

    public function hasStruct(string $name): bool
    {
        return isset($this->structs[$name]);
    }

    public function getStruct(string $name): ?Struct
    {
        return $this->structs[$name] ?? null;
    }

    /**
     * @return array<string, Struct>
     */
    public function getStructs(): array
    {
        return $this->structs;
    }

    // --- Type Aliases ---

    public function registerTypeAlias(string $name, TypeAlias $alias): void
    {
        $this->typeAliasData[$name] = $alias;
    }

    /**
     * @param array<string, TypeAlias> $aliases
     */
    public function setTypeAliases(array $aliases): void
    {
        $this->typeAliasData = $aliases;
    }

    /**
     * @return array<string, TypeAlias>
     */
    public function getTypeAliases(): array
    {
        return $this->typeAliasData;
    }

    // --- Namespaces ---

    public function hasNamespace(string $name): bool
    {
        return isset($this->namespaces[$name]);
    }

    public function registerNamespace(string $name, NamespaceDefinition $namespace): void
    {
        $this->namespaces[$name] = $namespace;
    }

    public function hasNamespaceConstant(string $namespace, string $constant): bool
    {
        return isset($this->namespaces[$namespace]) && $this->namespaces[$namespace]->hasConstant($constant);
    }

    public function getNamespaceConstant(string $namespace, string $constant): mixed
    {
        return $this->namespaces[$namespace]->getConstantValue($constant);
    }

    /**
     * @return array<string, NamespaceDefinition>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    // --- Resolving Refs ---

    public function isResolvingRef(string $refKey): bool
    {
        return isset($this->resolvingRefs[$refKey]);
    }

    public function pushResolvingRef(string $refKey): void
    {
        $this->resolvingRefs[$refKey] = true;
    }

    public function popResolvingRef(string $refKey): void
    {
        unset($this->resolvingRefs[$refKey]);
    }

    // --- Identifier Constants ---

    public function hasIdentifierConstant(string $name): bool
    {
        return isset($this->identifierConstants[$name]);
    }

    public function getIdentifierConstant(string $name): string
    {
        return $this->identifierConstants[$name];
    }

    public function setIdentifierConstant(string $name, string $value): void
    {
        $this->identifierConstants[$name] = $value;
    }

    // --- Value Constants ---

    public function hasValueConstant(string $name): bool
    {
        return isset($this->valueConstants[$name]);
    }

    public function getValueConstant(string $name): ConstantNode
    {
        return $this->valueConstants[$name];
    }

    public function setValueConstant(string $name, ConstantNode $value): void
    {
        $this->valueConstants[$name] = $value;
    }

    // --- Implicit Type Aliases ---

    public function registerImplicitTypeAlias(string $name, string $targetType): void
    {
        $this->implicitTypeAliases[$name] = $targetType;
    }

    /**
     * @return array<string, string>
     */
    public function getImplicitTypeAliases(): array
    {
        return $this->implicitTypeAliases;
    }

    // --- Source Code Map ---

    public function setSourceCode(string $key, string $code): void
    {
        $this->sourceCodeMap[$key] = $code;
    }

    public function getSourceCode(?string $key): ?string
    {
        return $this->sourceCodeMap[$key ?? ''] ?? null;
    }
}
