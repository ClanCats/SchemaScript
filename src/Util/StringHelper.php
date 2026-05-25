<?php

namespace ClanCats\SchemaScript\Util;

class StringHelper
{
    public static function toPascalCase(string $name): string
    {
        $name = (string) preg_replace('/_{2,}/', '_', $name);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
}
