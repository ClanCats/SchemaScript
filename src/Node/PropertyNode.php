<?php

namespace ClanCats\SchemaScript\Node;

use ClanCats\SchemaScript\Node\Type\TypeNode;

class PropertyNode extends BaseNode
{
    protected string $name;

    protected TypeNode $type;

    protected bool $isOptional = false;

    /**
     * @var array<AnnotationNode>
     */
    protected array $annotations = [];

    public function __construct(string $name, TypeNode $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): TypeNode
    {
        return $this->type;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): void
    {
        $this->isOptional = $isOptional;
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
        $visitor->visitProperty($this);
    }
}
