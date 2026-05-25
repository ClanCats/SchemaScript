<?php

namespace ClanCats\SchemaScript\Node;

class ConstantNode extends BaseNode
{
    protected string $name;
    private ?BaseNode $value = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setValue(?BaseNode $value): void
    {
        $this->value = $value;
    }

    public function getValue(): ?BaseNode
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitConstant($this);
    }
}
