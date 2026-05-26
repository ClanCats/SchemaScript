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

    /**
     * Resolves the read and write keys for a property based on mapping strategy and direction.
     *
     * When the direction indicates reading from the external format (e.g., 'fromArray', 'interfaceToLocal'),
     * the keys are swapped so that the external key is read and the local key is written.
     *
     * @param bool $invert When true, swaps the read/write keys (used for "from external" directions)
     * @return array{string, string} [readKey, writeKey]
     */
    public static function resolveReadWriteKeys(
        Definition $definition,
        string $mapFrom,
        string $mapTo,
        string $propertyName,
        AnnotationCollection $annotations,
        bool $invert,
    ): array {
        $fromKey = self::resolve($definition, $mapFrom, $propertyName, $annotations);
        $toKey = self::resolve($definition, $mapTo, $propertyName, $annotations);

        if ($invert) {
            return [$toKey, $fromKey];
        }

        return [$fromKey, $toKey];
    }
}
