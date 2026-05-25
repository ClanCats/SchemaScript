<?php

namespace ClanCats\SchemaScript\Node;

class AnnotationNode extends BaseNode
{
    protected string $name;

    /**
     * @var array<BaseNode>
     */
    protected array $arguments;

    /**
     * @param array<BaseNode> $arguments
     */
    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<BaseNode>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitAnnotation($this);
    }
}
