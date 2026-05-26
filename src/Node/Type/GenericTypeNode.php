<?php

namespace ClanCats\SchemaScript\Node\Type;

use ClanCats\SchemaScript\Node\NodeVisitorInterface;

class GenericTypeNode extends TypeNode
{
    protected string $name;

    /**
     * @var array<TypeNode>
     */
    protected array $arguments;

    /**
     * @param array<TypeNode> $arguments
     */
    public function __construct(string $name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<TypeNode>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitGenericType($this);
    }
}
