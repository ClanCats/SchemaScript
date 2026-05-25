<?php

namespace ClanCats\SchemaScript\Node;

class ReferenceNode extends BaseNode
{
    /** @var array<string> */
    protected array $parts;

    public function __construct(string ...$parts)
    {
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('ReferenceNode requires at least 2 parts (namespace + constant).');
        }
        $this->parts = $parts;
    }

    public function getNamespace(): string
    {
        return implode('::', array_slice($this->parts, 0, -1));
    }

    public function getConstant(): string
    {
        return $this->parts[count($this->parts) - 1];
    }

    /**
     * @return array<string>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitReference($this);
    }
}
