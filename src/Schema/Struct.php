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
     * @var array<array{key: string, value: mixed, attributes: AnnotationCollection}>
     */
    protected array $metadata;

    /**
     * @param array<StructProperty> $properties
     * @param array<array{key: string, value: mixed, attributes: AnnotationCollection}> $metadata
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
     * @return array<array{key: string, value: mixed, attributes: AnnotationCollection}>
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'properties' => array_map(fn(StructProperty $p) => $p->toArray(), $this->properties),
        ];

        if ($this->isInline) {
            $data['inline'] = true;
        }

        if ($this->metadata) {
            $data['metadata'] = array_map(function (array $entry) {
                $entry['attributes'] = $entry['attributes']->toArray();
                return $entry;
            }, $this->metadata);
        }

        return $data;
    }
}
