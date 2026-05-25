<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeVisitorInterface;
use Closure;

/**
 * @implements TypeVisitorInterface<string>
 */
class PhpMappersCastVisitor implements TypeVisitorInterface
{
    /**
     * @param Closure(Struct, string): string $inlineCastRenderer
     */
    public function __construct(
        private PhpMappersContext $ctx,
        private Definition $definition,
        private string $direction,
        private string $access,
        private Closure $inlineCastRenderer,
    ) {}

    private function withAccess(string $access): self
    {
        return new self($this->ctx, $this->definition, $this->direction, $access, $this->inlineCastRenderer);
    }

    public function visitNullable(Type $innerType): string
    {
        $inner = $innerType->accept($this);
        return "({$this->access} !== null ? {$inner} : null)";
    }

    public function visitArray(Type $elementType): string
    {
        $elementCast = $elementType->accept($this->withAccess('$v'));
        return "array_map(fn(\$v) => {$elementCast}, {$this->access})";
    }

    /**
     * @param array<Type> $types
     */
    public function visitUnion(array $types): string
    {
        return $this->access;
    }

    public function visitSimple(string $name): string
    {
        $alias = $this->definition->getTypeAlias($name);
        $phpType = $alias?->getLangType('php');
        if ($phpType === null) {
            throw new GeneratorException(sprintf('No PHP type mapping found for type "%s"', $name));
        }
        return self::castExpression($phpType, $this->access);
    }

    public function visitReference(string $name): string
    {
        $struct = $this->definition->getStruct($name);
        if ($struct !== null && $struct->isInline()) {
            if (isset($this->ctx->pubStructToMapper[$name])) {
                $mapperName = $this->ctx->pubStructToMapper[$name];
                return $mapperName . "Mapper::{$this->direction}({$this->access})";
            }
            return ($this->inlineCastRenderer)($struct, $this->access);
        }
        $phpName = str_replace('/', '', $name);
        return $phpName . "Mapper::{$this->direction}({$this->access})";
    }

    public function visitAlias(string $name): string
    {
        $alias = $this->definition->getTypeAlias($name);
        $phpType = $alias?->getLangType('php');
        if ($phpType !== null) {
            return self::castExpression($phpType, $this->access);
        }
        $resolvedType = $this->definition->getTypeAliasResolvedType($name);
        if ($resolvedType !== null) {
            return $resolvedType->accept($this);
        }
        return $this->access;
    }

    public function visitStringLiteral(string $value): string
    {
        return "(string) {$this->access}";
    }

    public static function castExpression(string $typeName, string $access): string
    {
        return match ($typeName) {
            'int' => "(int) {$access}",
            'float' => "(float) {$access}",
            'string' => "(string) {$access}",
            'bool' => "(bool) {$access}",
            default => $access,
        };
    }
}
