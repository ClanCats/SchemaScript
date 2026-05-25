<?php

namespace ClanCats\SchemaScript\Node;

class ImportNode extends BaseNode
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitImport($this);
    }
}
