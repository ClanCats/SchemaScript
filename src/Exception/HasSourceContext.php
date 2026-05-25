<?php

namespace ClanCats\SchemaScript\Exception;

trait HasSourceContext
{
    protected ?int $sourceLine = null;
    protected ?int $sourceColumn = null;
    protected ?int $sourceLength = null;
    protected ?string $sourceFile = null;
    protected ?string $sourceCode = null;

    public function setSourceContext(int $line, int $column, ?string $filename, ?string $sourceCode, ?int $length = null): self
    {
        $this->sourceLine = $line;
        $this->sourceColumn = $column;
        $this->sourceFile = $filename;
        $this->sourceCode = $sourceCode;
        $this->sourceLength = $length;
        return $this;
    }

    public function getSourceLine(): ?int
    {
        return $this->sourceLine;
    }

    public function getSourceColumn(): ?int
    {
        return $this->sourceColumn;
    }

    public function getSourceLength(): ?int
    {
        return $this->sourceLength;
    }

    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
    }

    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
