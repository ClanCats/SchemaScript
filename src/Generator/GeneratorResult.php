<?php

namespace ClanCats\SchemaScript\Generator;

class GeneratorResult
{
    /**
     * @var array<string, string>
     */
    private array $files = [];

    public function addFile(string $filename, string $content): void
    {
        if (isset($this->files[$filename])) {
            throw new \RuntimeException(sprintf('Generator output file collision: "%s" already exists', $filename));
        }
        $this->files[$filename] = $content;
    }

    /**
     * @return array<string, string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}
