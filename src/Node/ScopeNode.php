<?php

namespace ClanCats\SchemaScript\Node;

class ScopeNode extends BaseNode
{
    /**
     * @var array<ImportNode>
     */
    protected array $imports = [];

    /**
     * @var array<MetadataEntryNode>
     */
    protected array $metadata = [];

    /**
     * @var array<TypeAliasNode>
     */
    protected array $typeAliases = [];

    /**
     * @var array<NamespaceNode>
     */
    protected array $namespaces = [];

    /**
     * @var array<ConstantNode>
     */
    protected array $constants = [];

    /**
     * @var array<ModelDefinitionNode>
     */
    protected array $models = [];

    /**
     * @return array<ImportNode>
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    public function addImport(ImportNode $import): void
    {
        $this->imports[] = $import;
    }

    /**
     * @return array<MetadataEntryNode>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function addMetadata(MetadataEntryNode $metadata): void
    {
        $this->metadata[] = $metadata;
    }

    /**
     * @return array<TypeAliasNode>
     */
    public function getTypeAliases(): array
    {
        return $this->typeAliases;
    }

    public function addTypeAlias(TypeAliasNode $alias): void
    {
        $this->typeAliases[] = $alias;
    }

    /**
     * @param array<TypeAliasNode> $aliases
     */
    public function setTypeAliases(array $aliases): void
    {
        $this->typeAliases = $aliases;
    }

    /**
     * @return array<NamespaceNode>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    public function addNamespace(NamespaceNode $namespace): void
    {
        $this->namespaces[] = $namespace;
    }

    /**
     * @return array<ConstantNode>
     */
    public function getConstants(): array
    {
        return $this->constants;
    }

    public function addConstant(ConstantNode $constant): void
    {
        $this->constants[] = $constant;
    }

    /**
     * @return array<ModelDefinitionNode>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function addModel(ModelDefinitionNode $model): void
    {
        $this->models[] = $model;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitScope($this);
    }
}
