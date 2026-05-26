<?php

namespace ClanCats\SchemaScript\Schema;

class ResolvedTypeInfo
{
    /**
     * @param array<string, string> $langTypes
     */
    public function __construct(
        private bool $isPublic,
        private array $langTypes,
        private ?Type $resolvedType,
    ) {}

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function getLangType(string $lang): ?string
    {
        return $this->langTypes[$lang] ?? null;
    }

    public function getResolvedType(): ?Type
    {
        return $this->resolvedType;
    }
}
