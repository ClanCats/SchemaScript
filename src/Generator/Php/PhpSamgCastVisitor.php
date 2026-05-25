<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeVisitorInterface;

/**
 * @implements TypeVisitorInterface<string>
 */
class PhpSamgCastVisitor implements TypeVisitorInterface
{
    public function __construct(
        private Definition $definition,
        private string $direction,
        private string $access,
    ) {}

    private function withAccess(string $access): self
    {
        return new self($this->definition, $this->direction, $access);
    }

    public function visitNullable(Type $innerType): string
    {
        $innerCast = $innerType->accept($this);
        return "(!isset({$this->access})) ? null : {$innerCast}";
    }

    public function visitArray(Type $elementType): string
    {
        if ($elementType->isReference()) {
            $modelName = str_replace('/', '', $elementType->getName() ?? '');
            $mapMethod = $this->resolveMapMethod();
            return "array_map(fn(\$v) => {$modelName}Map::{$mapMethod}(\$v), {$this->access} ?? [])";
        }
        $elementCast = $elementType->accept($this->withAccess('$v'));
        return "array_map(fn(\$v) => {$elementCast}, {$this->access} ?? [])";
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): string
    {
        return "{$this->access} ?? null";
    }

    public function visitSimple(string $name): string
    {
        return self::primitiveCast($name, $this->access);
    }

    public function visitReference(string $name): string
    {
        $modelName = str_replace('/', '', $name);
        $mapMethod = $this->resolveMapMethod();
        return "{$modelName}Map::{$mapMethod}({$this->access})";
    }

    public function visitAlias(string $name): string
    {
        $alias = $this->definition->getTypeAlias($name);
        $phpType = $alias?->getLangType('php');
        if ($phpType !== null) {
            return self::primitiveCast($phpType, $this->access);
        }
        $resolvedType = $this->definition->getTypeAliasResolvedType($name);
        if ($resolvedType !== null) {
            return $resolvedType->accept($this);
        }
        return "{$this->access} ?? null";
    }

    public function visitStringLiteral(string $value): string
    {
        return "(string) ({$this->access} ?? null)";
    }

    private function resolveMapMethod(): string
    {
        return $this->direction === 'localToInterface' ? 'localToInterface' : 'interfaceToLocal';
    }

    private static function primitiveCast(string $typeName, string $access): string
    {
        return match ($typeName) {
            'int' => "(int) ({$access} ?? null)",
            'float' => "(float) ({$access} ?? null)",
            'string' => "(string) ({$access} ?? null)",
            'bool' => "(bool) ({$access} ?? null)",
            default => "{$access} ?? null",
        };
    }
}
