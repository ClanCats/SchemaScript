<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Schema\Type;

class PhpSamgCastVisitor extends PhpBaseCastVisitor
{
    protected function createWithAccess(string $access): PhpBaseCastVisitor
    {
        return new self($this->resolved, $this->direction, $access);
    }

    protected function castPrimitive(string $typeName, string $access): string
    {
        return match ($typeName) {
            'int' => "(int) ({$access} ?? null)",
            'float' => "(float) ({$access} ?? null)",
            'string' => "(string) ({$access} ?? null)",
            'bool' => "(bool) ({$access} ?? null)",
            default => "{$access} ?? null",
        };
    }

    protected function fallbackAccess(): string
    {
        return "{$this->access} ?? null";
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
        $elementCast = $elementType->accept($this->createWithAccess('$v'));
        return "array_map(fn(\$v) => {$elementCast}, {$this->access} ?? [])";
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): string
    {
        return "{$this->access} ?? null";
    }

    public function visitReference(string $name): string
    {
        $modelName = str_replace('/', '', $name);
        $mapMethod = $this->resolveMapMethod();
        return "{$modelName}Map::{$mapMethod}({$this->access})";
    }

    private function resolveMapMethod(): string
    {
        return $this->direction === 'localToInterface' ? 'localToInterface' : 'interfaceToLocal';
    }

    /**
     * @param array<Type> $typeArguments
     */
    public function visitGeneric(string $baseName, array $typeArguments): string
    {
        $struct = $this->resolved->getStruct($baseName);
        if ($struct !== null) {
            $langAnnotation = $struct->getAnnotation('lang.php');
            if ($langAnnotation !== null) {
                $phpType = $langAnnotation->getArguments()[0] ?? null;
                if ($phpType === 'array' && count($typeArguments) >= 2) {
                    $valueCast = $typeArguments[1]->accept($this->createWithAccess('$v'));
                    return "array_map(fn(\$v) => {$valueCast}, {$this->access} ?? [])";
                }
            }
        }

        return "{$this->access} ?? null";
    }
}
