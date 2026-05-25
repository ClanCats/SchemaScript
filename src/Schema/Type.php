<?php

namespace ClanCats\SchemaScript\Schema;

class Type
{
    const KIND_SIMPLE = 'simple';
    const KIND_REFERENCE = 'reference';
    const KIND_ALIAS = 'alias';
    const KIND_ARRAY = 'array';
    const KIND_NULLABLE = 'nullable';
    const KIND_UNION = 'union';
    const KIND_STRING_LITERAL = 'string_literal';

    protected string $kind;

    protected ?string $name;

    protected ?Type $innerType;

    /**
     * @var array<Type>
     */
    protected array $unionTypes;

    /**
     * @param array<Type> $unionTypes
     */
    private function __construct(string $kind, ?string $name = null, ?Type $innerType = null, array $unionTypes = [])
    {
        $this->kind = $kind;
        $this->name = $name;
        $this->innerType = $innerType;
        $this->unionTypes = $unionTypes;
    }

    public static function simple(string $name): self
    {
        return new self(self::KIND_SIMPLE, $name);
    }

    public static function reference(string $structName): self
    {
        return new self(self::KIND_REFERENCE, $structName);
    }

    public static function alias(string $aliasName): self
    {
        return new self(self::KIND_ALIAS, $aliasName);
    }

    public static function array(Type $elementType): self
    {
        return new self(self::KIND_ARRAY, null, $elementType);
    }

    public static function nullable(Type $innerType): self
    {
        return new self(self::KIND_NULLABLE, null, $innerType);
    }

    /**
     * @param array<Type> $types
     */
    public static function union(array $types): self
    {
        return new self(self::KIND_UNION, null, null, $types);
    }

    public static function stringLiteral(string $value): self
    {
        return new self(self::KIND_STRING_LITERAL, $value);
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getInnerType(): ?Type
    {
        return $this->innerType;
    }

    /**
     * @return array<Type>
     */
    public function getUnionTypes(): array
    {
        return $this->unionTypes;
    }

    public function isNullable(): bool
    {
        return $this->kind === self::KIND_NULLABLE;
    }

    public function isAlias(): bool
    {
        return $this->kind === self::KIND_ALIAS;
    }

    public function isSimple(): bool
    {
        return $this->kind === self::KIND_SIMPLE;
    }

    public function isReference(): bool
    {
        return $this->kind === self::KIND_REFERENCE;
    }

    public function isArray(): bool
    {
        return $this->kind === self::KIND_ARRAY;
    }

    public function isUnion(): bool
    {
        return $this->kind === self::KIND_UNION;
    }

    public function isStringLiteral(): bool
    {
        return $this->kind === self::KIND_STRING_LITERAL;
    }
}
