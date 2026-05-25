<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;

class ArrayTypeNode extends TypeNode
{
    protected TypeNode $elementType;

    public function __construct(TypeNode $elementType)
    {
        $this->elementType = $elementType;
    }

    public function getElementType(): TypeNode
    {
        return $this->elementType;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitArrayType($this);
    }
}
