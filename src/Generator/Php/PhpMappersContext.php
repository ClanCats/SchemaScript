<?php

namespace ClanCats\SchemaScript\Generator\Php;

class PhpMappersContext
{
    /**
     * @var array<string, string>
     */
    public array $pubStructToMapper = [];

    public ?string $mapFrom = null;
    public ?string $mapTo = null;
}
