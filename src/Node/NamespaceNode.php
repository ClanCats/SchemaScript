<?php

namespace ClanCats\SchemaScript\Node;

class NamespaceNode extends BaseNode
{
    protected string $name;

    /**
     * @var array<ConstantNode>
     */
    protected array $constants = [];

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

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitNamespace($this);
    }
}
