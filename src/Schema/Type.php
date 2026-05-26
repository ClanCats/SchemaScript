<?php

namespace ClanCats\SchemaScript\Schema;

class Type
{
    protected TypeKind $kind;

    protected ?string $name;

    protected ?Type $innerType;

    /**
     * @var array<Type>
     */
    protected array $unionTypes;

    /**
     * @var array<Type>
     */
    protected array $typeArguments;

    /**
     * @param array<Type> $unionTypes
     * @param array<Type> $typeArguments
     */
    private function __construct(TypeKind $kind, ?string $name = null, ?Type $innerType = null, array $unionTypes = [], array $typeArguments = [])
    {
        $this->kind = $kind;
        $this->name = $name;
        $this->innerType = $innerType;
        $this->unionTypes = $unionTypes;
        $this->typeArguments = $typeArguments;
    }

    public static function simple(string $name): self
    {
        return new self(TypeKind::Simple, $name);
    }

    public static function reference(string $structName): self
    {
        return new self(TypeKind::Reference, $structName);
    }

    public static function alias(string $aliasName): self
    {
        return new self(TypeKind::Alias, $aliasName);
    }

    public static function array(Type $elementType): self
    {
        return new self(TypeKind::Array, null, $elementType);
    }

    public static function nullable(Type $innerType): self
    {
        return new self(TypeKind::Nullable, null, $innerType);
    }

    /**
     * @param array<Type> $types
     */
    public static function union(array $types): self
    {
        return new self(TypeKind::Union, null, null, $types);
    }

    public static function stringLiteral(string $value): self
    {
        return new self(TypeKind::StringLiteral, $value);
    }

    public static function typeParameter(string $name): self
    {
        return new self(TypeKind::TypeParameter, $name);
    }

    /**
     * @param array<Type> $typeArguments
     */
    public static function generic(string $baseName, array $typeArguments): self
    {
        return new self(TypeKind::Generic, $baseName, null, [], $typeArguments);
    }

    public function getKind(): TypeKind
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
        return $this->kind === TypeKind::Nullable;
    }

    public function isAlias(): bool
    {
        return $this->kind === TypeKind::Alias;
    }

    public function isSimple(): bool
    {
        return $this->kind === TypeKind::Simple;
    }

    public function isReference(): bool
    {
        return $this->kind === TypeKind::Reference;
    }

    public function isArray(): bool
    {
        return $this->kind === TypeKind::Array;
    }

    public function isUnion(): bool
    {
        return $this->kind === TypeKind::Union;
    }

    public function isStringLiteral(): bool
    {
        return $this->kind === TypeKind::StringLiteral;
    }

    public function isTypeParameter(): bool
    {
        return $this->kind === TypeKind::TypeParameter;
    }

    public function isGeneric(): bool
    {
        return $this->kind === TypeKind::Generic;
    }

    /**
     * @return array<Type>
     */
    public function getTypeArguments(): array
    {
        return $this->typeArguments;
    }

    /**
     * @template T
     * @param TypeVisitorInterface<T> $visitor
     * @return T
     */
    public function accept(TypeVisitorInterface $visitor): mixed
    {
        return match ($this->kind) {
            TypeKind::Nullable => $visitor->visitNullable($this->innerType ?? self::simple('mixed')),
            TypeKind::Array => $visitor->visitArray($this->innerType ?? self::simple('mixed')),
            TypeKind::Union => $visitor->visitUnion($this->unionTypes),
            TypeKind::Simple => $visitor->visitSimple($this->name ?? ''),
            TypeKind::Reference => $visitor->visitReference($this->name ?? ''),
            TypeKind::Alias => $visitor->visitAlias($this->name ?? ''),
            TypeKind::StringLiteral => $visitor->visitStringLiteral($this->name ?? ''),
            TypeKind::TypeParameter => $visitor->visitTypeParameter($this->name ?? ''),
            TypeKind::Generic => $visitor->visitGeneric($this->name ?? '', $this->typeArguments),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['kind' => $this->kind->value];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->innerType !== null) {
            $data['innerType'] = $this->innerType->toArray();
        }

        if ($this->unionTypes) {
            $data['types'] = array_map(fn(Type $t) => $t->toArray(), $this->unionTypes);
        }

        if ($this->typeArguments) {
            $data['typeArguments'] = array_map(fn(Type $t) => $t->toArray(), $this->typeArguments);
        }

        return $data;
    }
}
