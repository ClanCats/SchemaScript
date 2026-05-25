<?php

namespace ClanCats\SchemaScript\Node;

class ModelDefinitionNode extends BaseNode
{
    protected string $name;

    /**
     * @var array<MetadataEntryNode>
     */
    protected array $metadata = [];

    /**
     * @var array<PropertyNode>
     */
    protected array $properties = [];

    /**
     * @var array<TypeAliasNode>
     */
    protected array $typeAliases = [];

    /**
     * @var array<ModelDefinitionNode>
     */
    protected array $childModels = [];

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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
     * @return array<PropertyNode>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(PropertyNode $property): void
    {
        $this->properties[] = $property;
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
     * @return array<ModelDefinitionNode>
     */
    public function getChildModels(): array
    {
        return $this->childModels;
    }

    public function addChildModel(ModelDefinitionNode $model): void
    {
        $this->childModels[] = $model;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitModelDefinition($this);
    }
}
