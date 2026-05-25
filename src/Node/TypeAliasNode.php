<?php

namespace ClanCats\SchemaScript\Node;

use ClanCats\SchemaScript\Node\Type\TypeNode;

class TypeAliasNode extends BaseNode
{
    protected string $name;

    /**
     * @var array<AnnotationNode>
     */
    protected array $annotations = [];

    protected ?TypeNode $typeDefinition = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
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

    public function getTypeDefinition(): ?TypeNode
    {
        return $this->typeDefinition;
    }

    public function setTypeDefinition(?TypeNode $typeDefinition): void
    {
        $this->typeDefinition = $typeDefinition;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitTypeAlias($this);
    }
}
