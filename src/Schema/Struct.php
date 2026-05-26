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
     * @var array<MetadataEntry>
     */
    protected array $metadata;

    protected AnnotationCollection $annotations;

    /**
     * @var array<string>
     */
    protected array $typeParameters;

    protected bool $isPrivate;

    /**
     * @param array<StructProperty> $properties
     * @param array<MetadataEntry> $metadata
     * @param array<string> $typeParameters
     */
    public function __construct(string $name, bool $isInline, array $properties = [], array $metadata = [], AnnotationCollection $annotations = new AnnotationCollection(), array $typeParameters = [], bool $isPrivate = false)
    {
        $this->name = $name;
        $this->isInline = $isInline;
        $this->properties = $properties;
        $this->metadata = $metadata;
        $this->annotations = $annotations;
        $this->typeParameters = $typeParameters;
        $this->isPrivate = $isPrivate;
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
     * @return array<MetadataEntry>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getAnnotations(): AnnotationCollection
    {
        return $this->annotations;
    }

    /**
     * @return array<string>
     */
    public function getTypeParameters(): array
    {
        return $this->typeParameters;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function isGeneric(): bool
    {
        return !empty($this->typeParameters);
    }

    public function hasAnnotation(string $name): bool
    {
        return $this->annotations->has($name);
    }

    public function getAnnotation(string $name): ?Annotation
    {
        return $this->annotations->get($name);
    }

    /**
     * @return mixed
     */
    public function getMetadataValue(string $key)
    {
        foreach ($this->metadata as $entry) {
            if ($entry->getKey() === $key) {
                return $entry->getValue();
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

        if ($this->isPrivate) {
            $data['private'] = true;
        }

        if ($this->typeParameters) {
            $data['typeParameters'] = $this->typeParameters;
        }

        if ($this->metadata) {
            $data['metadata'] = array_map(fn(MetadataEntry $e) => $e->toArray(), $this->metadata);
        }

        if (!$this->annotations->isEmpty()) {
            $data['annotations'] = $this->annotations->toArray();
        }

        return $data;
    }
}
