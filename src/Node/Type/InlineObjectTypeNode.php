<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;
use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;

class InlineObjectTypeNode extends TypeNode
{
    /**
     * @var array<PropertyNode>
     */
    protected array $properties = [];

    /**
     * @var array<MetadataEntryNode>
     */
    protected array $metadata = [];

    private ?string $explicitName = null;

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

    public function setExplicitName(?string $name): void
    {
        $this->explicitName = $name;
    }

    public function getExplicitName(): ?string
    {
        return $this->explicitName;
    }

    public function hasExplicitName(): bool
    {
        return $this->explicitName !== null;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitInlineObjectType($this);
    }
}
