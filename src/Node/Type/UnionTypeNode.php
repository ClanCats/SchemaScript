<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;

class UnionTypeNode extends TypeNode
{
    /**
     * @var array<TypeNode>
     */
    protected array $types;

    /**
     * @param array<TypeNode> $types
     */
    public function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * @return array<TypeNode>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitUnionType($this);
    }
}
