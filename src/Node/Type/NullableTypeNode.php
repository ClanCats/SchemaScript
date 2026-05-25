<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;

class NullableTypeNode extends TypeNode
{
    protected TypeNode $innerType;

    public function __construct(TypeNode $innerType)
    {
        $this->innerType = $innerType;
    }

    public function getInnerType(): TypeNode
    {
        return $this->innerType;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitNullableType($this);
    }
}
