<?php

namespace ClanCats\SchemaScript\Node;

abstract class BaseNode
{
    protected ?int $sourceLine = null;
    protected ?int $sourceColumn = null;
    protected ?string $sourceFile = null;

    public function setSourcePosition(int $line, int $column, ?string $filename = null): void
    {
        $this->sourceLine = $line;
        $this->sourceColumn = $column;
        $this->sourceFile = $filename;
    }

    public function getSourceLine(): ?int
    {
        return $this->sourceLine;
    }

    public function getSourceColumn(): ?int
    {
        return $this->sourceColumn;
    }

    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
    }

    abstract public function accept(NodeVisitorInterface $visitor): void;
}
