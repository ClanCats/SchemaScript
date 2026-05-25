<?php

namespace ClanCats\SchemaScript\Schema;

class Struct
{
    protected string $name;

    protected bool $isInline;

    /**
     * @var array<StructProperty>
     */
    protected array $properties;

    /**
     * @var array<array{key: string, value: mixed, attributes: array<string, mixed[]>}>
     */
    protected array $metadata;

    /**
     * @param array<StructProperty> $properties
     * @param array<array{key: string, value: mixed, attributes: array<string, mixed[]>}> $metadata
     */
    public function __construct(string $name, bool $isInline, array $properties = [], array $metadata = [])
    {
        $this->name = $name;
        $this->isInline = $isInline;
        $this->properties = $properties;
        $this->metadata = $metadata;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isInline(): bool
    {
        return $this->isInline;
    }

    /**
     * @return array<StructProperty>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array<array{key: string, value: mixed, attributes: array<string, mixed[]>}>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return mixed
     */
    public function getMetadataValue(string $key)
    {
        foreach ($this->metadata as $entry) {
            if ($entry['key'] === $key) {
                return $entry['value'];
            }
        }
        return null;
    }
}
