<?php

namespace ClanCats\SchemaScript\Workbench;

class Str
{
    /**
     * @return string[]
     */
    private static function splitWords(string $name): array
    {
        $name = str_replace('-', '_', $name);
        $name = (string) preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $name);
        $name = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name);
        $name = strtolower($name);
        return array_filter(explode('_', $name), fn(string $w) => $w !== '');
    }

    public static function toPascalCase(string $name): string
    {
        return implode('', array_map('ucfirst', self::splitWords($name)));
    }

    public static function toCamelCase(string $name): string
    {
        return lcfirst(self::toPascalCase($name));
    }

    public static function toSnakeCase(string $name): string
    {
        return implode('_', self::splitWords($name));
    }

    public static function toScreamingSnakeCase(string $name): string
    {
        return strtoupper(self::toSnakeCase($name));
    }

    public static function toKebabCase(string $name): string
    {
        return implode('-', self::splitWords($name));
    }
}
