<?php

namespace ClanCats\SchemaScript\Importer\Samg;

use ClanCats\SchemaScript\Exception\ImporterException;
use ClanCats\SchemaScript\Importer\ImporterInterface;
use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\MetadataEntry;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Workbench\Str;

class SamgImporter implements ImporterInterface
{
    public function getName(): string
    {
        return 'samg';
    }

    public function getDescription(): string
    {
        return 'Imports SAMG.md schema definition files';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function import(string $filePath, array $options = []): Definition
    {
        if (!file_exists($filePath)) {
            throw new ImporterException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new ImporterException("Could not read file: {$filePath}");
        }

        $parser = new SamgParser();
        $parsed = $parser->parse($content);

        return $this->buildDefinition($parsed);
    }

    /**
     * @param array{config: array<string, string>, models: array<string, array{config: array<string, string>, properties: list<array{interfaceName: string, localName: string, type: string, nullable: bool, relationship: ?string, relationshipModel: ?string}>}>} $parsed
     */
    private function buildDefinition(array $parsed): Definition
    {
        $metadata = $this->buildMetadata($parsed['config']);

        $structs = [];
        foreach ($parsed['models'] as $modelName => $modelData) {
            $properties = [];
            foreach ($modelData['properties'] as $prop) {
                $properties[] = $this->buildProperty($prop);
            }

            $modelMetadata = [];
            foreach ($modelData['config'] as $key => $value) {
                $modelMetadata[] = new MetadataEntry($key, $value, new AnnotationCollection());
            }

            $structs[$modelName] = new Struct($modelName, false, $properties, $modelMetadata);
        }

        return new Definition($metadata, [], [], $structs);
    }

    /**
     * @param array<string, string> $config
     * @return list<MetadataEntry>
     */
    private function buildMetadata(array $config): array
    {
        $metadata = [];

        $samgOptions = [];

        $pathMaps = $config['path.maps'] ?? null;
        if ($pathMaps !== null) {
            $samgOptions[] = new MetadataEntry('output', rtrim($pathMaps, '/') . '/', new AnnotationCollection());
        }

        $namespaceMaps = $config['namespace.maps'] ?? null;
        if ($namespaceMaps !== null) {
            $samgOptions[] = new MetadataEntry('namespace', rtrim($namespaceMaps, '\\') . '\\', new AnnotationCollection());
        }

        $samgOptions[] = new MetadataEntry('map_from', 'self', new AnnotationCollection());
        $samgOptions[] = new MetadataEntry('map_to', 'api', new AnnotationCollection());

        $mapBlock = [
            new MetadataEntry('api', [
                new MetadataEntry('strategy', 'MappingStrategy::snakeCase', new AnnotationCollection()),
            ], new AnnotationCollection()),
        ];
        $metadata[] = new MetadataEntry('map', $mapBlock, new AnnotationCollection());

        $generateBlock = [
            new MetadataEntry('php.samg', $samgOptions, new AnnotationCollection()),
        ];
        $metadata[] = new MetadataEntry('generate', $generateBlock, new AnnotationCollection());

        return $metadata;
    }

    /**
     * @param array{interfaceName: string, localName: string, type: string, nullable: bool, relationship: ?string, relationshipModel: ?string} $prop
     */
    private function buildProperty(array $prop): StructProperty
    {
        $type = $this->buildType($prop);

        $annotations = [];
        $expectedSnake = Str::toSnakeCase($prop['localName']);
        if ($prop['interfaceName'] !== $prop['localName'] && $prop['interfaceName'] !== $expectedSnake) {
            $annotations['map.api'] = new Annotation('map.api', [$prop['interfaceName']]);
        }

        return new StructProperty(
            $prop['localName'],
            $type,
            false,
            new AnnotationCollection($annotations),
        );
    }

    /**
     * @param array{interfaceName: string, localName: string, type: string, nullable: bool, relationship: ?string, relationshipModel: ?string} $prop
     */
    private function buildType(array $prop): Type
    {
        if ($prop['relationship'] !== null && $prop['relationshipModel'] !== null) {
            $refType = Type::reference($prop['relationshipModel']);

            if ($prop['relationship'] === '1:n') {
                $refType = Type::array($refType);
            }

            if ($prop['nullable']) {
                $refType = Type::nullable($refType);
            }

            return $refType;
        }

        $type = match ($prop['type']) {
            'string', 'int', 'bool', 'float', 'mixed' => Type::simple($prop['type']),
            'array' => Type::array(Type::simple('mixed')),
            default => Type::simple($prop['type']),
        };

        if ($prop['nullable']) {
            $type = Type::nullable($type);
        }

        return $type;
    }
}
