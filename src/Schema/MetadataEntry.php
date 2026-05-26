<?php

namespace ClanCats\SchemaScript\Schema;

class MetadataEntry
{
    public function __construct(
        protected string $key,
        protected mixed $value,
        protected AnnotationCollection $attributes,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getAttributes(): AnnotationCollection
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->serializeValue($this->value),
            'attributes' => $this->attributes->toArray(),
        ];
    }

    private function serializeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn($item) => $item instanceof self ? $item->toArray() : $this->serializeValue($item), $value);
        }
        return $value;
    }
}
