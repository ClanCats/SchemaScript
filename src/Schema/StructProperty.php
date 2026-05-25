<?php

namespace ClanCats\SchemaScript\Schema;

class StructProperty
{
    protected string $name;

    protected Type $type;

    protected bool $isOptional;

    /**
     * @var array<string, mixed[]>
     */
    protected array $annotations;

    /**
     * @param array<string, mixed[]> $annotations
     */
    public function __construct(string $name, Type $type, bool $isOptional, array $annotations = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->isOptional = $isOptional;
        $this->annotations = $annotations;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    /**
     * @return array<string, mixed[]>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function hasAnnotation(string $name): bool
    {
        return isset($this->annotations[$name]);
    }

    /**
     * @return mixed[]|null
     */
    public function getAnnotation(string $name): ?array
    {
        return $this->annotations[$name] ?? null;
    }
}
