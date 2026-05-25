<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;

class SimpleTypeNode extends TypeNode
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitSimpleType($this);
    }
}
