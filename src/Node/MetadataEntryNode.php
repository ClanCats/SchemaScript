<?php

namespace ClanCats\SchemaScript\Node;

class MetadataEntryNode extends BaseNode
{
    protected string $key;

    protected ?BaseNode $value;

    /**
     * @var array<AnnotationNode>
     */
    protected array $annotations = [];

    /**
     * @param array<AnnotationNode> $annotations
     */
    public function __construct(string $key, ?BaseNode $value = null, array $annotations = [])
    {
        $this->key = $key;
        $this->value = $value;
        $this->annotations = $annotations;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?BaseNode
    {
        return $this->value;
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

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitMetadataEntry($this);
    }
}
