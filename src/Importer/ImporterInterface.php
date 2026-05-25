<?php

namespace ClanCats\SchemaScript\Importer;

use ClanCats\SchemaScript\Schema\Definition;

interface ImporterInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function import(string $filePath, array $options = []): Definition;
}
