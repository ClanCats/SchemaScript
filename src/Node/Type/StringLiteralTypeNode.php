<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;

class StringLiteralTypeNode extends TypeNode
{
    protected string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitStringLiteralType($this);
    }
}
