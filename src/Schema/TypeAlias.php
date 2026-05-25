<?php

namespace ClanCats\SchemaScript\Schema;

class TypeAlias
{
    public function __construct(
        private string $name,
        private bool $isPublic,
        private ?Type $resolvedType,
        private AnnotationCollection $annotations = new AnnotationCollection()
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function getResolvedType(): ?Type
    {
        return $this->resolvedType;
    }

    public function getAnnotations(): AnnotationCollection
    {
        return $this->annotations;
    }

    public function getLangType(string $lang): ?string
    {
        return $this->annotations->getLangType($lang);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->isPublic) {
            $data['public'] = true;
        }

        if ($this->resolvedType !== null) {
            $data['resolvedType'] = $this->resolvedType->toArray();
        }

        if (!$this->annotations->isEmpty()) {
            $data['annotations'] = $this->annotations->toArray();
        }

        return $data;
    }
}
