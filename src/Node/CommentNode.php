<?php

namespace ClanCats\SchemaScript\Node;

class CommentNode extends BaseNode
{
    /**
     * @var array<string>
     */
    protected array $lines;

    /**
     * @param array<string> $lines
     */
    public function __construct(array $lines)
    {
        $this->lines = $lines;
    }

    /**
     * @return array<string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getText(): string
    {
        return implode("\n", $this->lines);
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitComment($this);
    }
}
