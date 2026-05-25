<?php

namespace ClanCats\SchemaScript\Generator;

use ClanCats\SchemaScript\Schema\Definition;

interface GeneratorInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param array<string, mixed> $options
     */
    public function generate(Definition $definition, array $options = []): GeneratorResult;
}
