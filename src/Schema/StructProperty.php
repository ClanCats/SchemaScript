<?php

namespace ClanCats\SchemaScript\Schema;

class StructProperty
{
    protected string $name;

    protected Type $type;

    protected bool $isOptional;

    protected AnnotationCollection $annotations;

    protected ?string $comment;

    public function __construct(string $name, Type $type, bool $isOptional, AnnotationCollection $annotations = new AnnotationCollection(), ?string $comment = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->isOptional = $isOptional;
        $this->annotations = $annotations;
        $this->comment = $comment;
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

    public function getAnnotations(): AnnotationCollection
    {
        return $this->annotations;
    }

    public function hasAnnotation(string $name): bool
    {
        return $this->annotations->has($name);
    }

    public function getAnnotation(string $name): ?Annotation
    {
        return $this->annotations->get($name);
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'type' => $this->type->toArray(),
        ];

        if ($this->isOptional) {
            $data['optional'] = true;
        }

        if (!$this->annotations->isEmpty()) {
            $data['annotations'] = $this->annotations->toArray();
        }

        if ($this->comment !== null) {
            $data['comment'] = $this->comment;
        }

        return $data;
    }
}
