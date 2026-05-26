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

    /**
     * @var array<AnnotationNode>
     */
    protected array $annotations = [];

    /**
     * @var array<string>
     */
    protected array $typeParameters = [];

    /**
     * @var array<\ClanCats\SchemaScript\Node\Type\TypeNode>
     */
    protected array $parentTypes = [];

    protected bool $isPrivate = false;

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

    /**
     * @return array<AnnotationNode>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @param array<AnnotationNode> $annotations
     */
    public function setAnnotations(array $annotations): void
    {
        $this->annotations = $annotations;
    }

    /**
     * @return array<string>
     */
    public function getTypeParameters(): array
    {
        return $this->typeParameters;
    }

    /**
     * @param array<string> $typeParameters
     */
    public function setTypeParameters(array $typeParameters): void
    {
        $this->typeParameters = $typeParameters;
    }

    /**
     * @return array<\ClanCats\SchemaScript\Node\Type\TypeNode>
     */
    public function getParentTypes(): array
    {
        return $this->parentTypes;
    }

    /**
     * @param array<\ClanCats\SchemaScript\Node\Type\TypeNode> $parentTypes
     */
    public function setParentTypes(array $parentTypes): void
    {
        $this->parentTypes = $parentTypes;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setPrivate(bool $isPrivate): void
    {
        $this->isPrivate = $isPrivate;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitModelDefinition($this);
    }
}
