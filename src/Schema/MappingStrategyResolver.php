<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Workbench\Str;

class MappingStrategyResolver
{
    public static function resolve(Definition $definition, string $mapName, string $propertyName, AnnotationCollection $annotations): string
    {
        if ($mapName === 'self') {
            return $propertyName;
        }

        $override = $annotations->getMapKey($mapName);
        if ($override !== null) {
            return $override;
        }

        $maps = $definition->findMetadataValue('map');
        if (!is_array($maps)) {
            return $propertyName;
        }

        $strategy = null;
        foreach ($maps as $entry) {
            if ($entry instanceof MetadataEntry && $entry->getKey() === $mapName) {
                $mapValue = $entry->getValue();
                if (is_array($mapValue)) {
                    foreach ($mapValue as $sub) {
                        if ($sub instanceof MetadataEntry && $sub->getKey() === 'strategy') {
                            $strategy = $sub->getValue();
                            break 2;
                        }
                    }
                }
            }
        }

        if ($strategy === null) {
            return $propertyName;
        }

        return match ($strategy) {
            'MappingStrategy::camelCase' => Str::toCamelCase($propertyName),
            'MappingStrategy::pascalCase' => Str::toPascalCase($propertyName),
            'MappingStrategy::snakeCase' => Str::toSnakeCase($propertyName),
            'MappingStrategy::screamingSnakeCase' => Str::toScreamingSnakeCase($propertyName),
            'MappingStrategy::kebabCase' => Str::toKebabCase($propertyName),
            default => $propertyName,
        };
    }
}
