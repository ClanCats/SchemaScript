<?php

namespace ClanCats\SchemaScript\Node;

class ReferenceNode extends BaseNode
{
    protected string $namespace;

    protected string $constant;

    public function __construct(string $namespace, string $constant)
    {
        $this->namespace = $namespace;
        $this->constant = $constant;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getConstant(): string
    {
        return $this->constant;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitReference($this);
    }
}
