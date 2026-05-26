<?php

namespace ClanCats\SchemaScript\Generator\Php;

use ClanCats\SchemaScript\Exception\GeneratorException;
use ClanCats\SchemaScript\Schema\ResolvedDefinition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\Type;
use Closure;

class PhpMappersCastVisitor extends PhpBaseCastVisitor
{
    /**
     * @param Closure(Struct, string): string $inlineCastRenderer
     */
    public function __construct(
        private PhpMappersContext $ctx,
        ResolvedDefinition $resolved,
        string $direction,
        string $access,
        private Closure $inlineCastRenderer,
    ) {
        parent::__construct($resolved, $direction, $access);
    }

    protected function createWithAccess(string $access): PhpBaseCastVisitor
    {
        return new self($this->ctx, $this->resolved, $this->direction, $access, $this->inlineCastRenderer);
    }

    protected function castPrimitive(string $typeName, string $access): string
    {
        return match ($typeName) {
            'int' => "(int) {$access}",
            'float' => "(float) {$access}",
            'string' => "(string) {$access}",
            'bool' => "(bool) {$access}",
            default => $access,
        };
    }

    public function visitNullable(Type $innerType): string
    {
        $inner = $innerType->accept($this);
        return "({$this->access} !== null ? {$inner} : null)";
    }

    public function visitSimple(string $name): string
    {
        $info = $this->resolved->getTypeInfo($name);
        $phpType = $info?->getLangType('php');
        if ($phpType === null) {
            throw new GeneratorException(sprintf('No PHP type mapping found for type "%s"', $name));
        }
        return $this->castPrimitive($phpType, $this->access);
    }

    public function visitReference(string $name): string
    {
        $struct = $this->resolved->getStruct($name);
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
}
