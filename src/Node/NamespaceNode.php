<?php

namespace ClanCats\SchemaScript\Node;

class NamespaceNode extends BaseNode
{
    protected string $name;

    /**
     * @var array<ConstantNode>
     */
    protected array $constants = [];

    /**
     * @var array<NamespaceNode>
     */
    protected array $children = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
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
     * @return array<NamespaceNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(NamespaceNode $child): void
    {
        $this->children[] = $child;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitNamespace($this);
    }
}
