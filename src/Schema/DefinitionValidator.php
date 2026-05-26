<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Exception\EvaluatorException;

class DefinitionValidator implements DefinitionVisitorInterface
{
    private Definition $definition;
    private TypeCollectorVisitor $typeCollector;

    /** @var list<string> */
    private array $errors = [];

    public function validate(Definition $definition): void
    {
        $this->definition = $definition;
        $this->errors = [];
        $this->typeCollector = new TypeCollectorVisitor();

        $walker = new DefinitionWalker();
        $walker->walk($definition, $this);

        if ($this->errors !== []) {
            throw new EvaluatorException(
                "Schema validation failed:\n - " . implode("\n - ", $this->errors)
            );
        }
    }

    public function visitDefinition(Definition $definition): void
    {
    }

    public function visitStruct(Struct $struct): void
    {
    }

    public function visitProperty(Struct $struct, StructProperty $property): void
    {
        $this->typeCollector->reset();
        $property->getType()->accept($this->typeCollector);

        foreach ($this->typeCollector->getReferences() as $ref) {
            if ($this->definition->getStruct($ref) === null) {
                $this->errors[] = sprintf(
                    "Property '%s' on struct '%s' references unknown struct '%s'",
                    $property->getName(),
                    $struct->getName(),
                    $ref
                );
            }
        }

        foreach ($this->typeCollector->getGenericUsages() as $genericName => $argCount) {
            $genericStruct = $this->definition->getStruct($genericName);
            if ($genericStruct === null) {
                continue;
            }
            if (!$genericStruct->isGeneric()) {
                $this->errors[] = sprintf(
                    "Property '%s' on struct '%s' uses type arguments on non-generic struct '%s'",
                    $property->getName(),
                    $struct->getName(),
                    $genericName
                );
                continue;
            }
            $expectedCount = count($genericStruct->getTypeParameters());
            if ($argCount !== $expectedCount) {
                $this->errors[] = sprintf(
                    "Property '%s' on struct '%s' passes %d type argument(s) to '%s', expected %d",
                    $property->getName(),
                    $struct->getName(),
                    $argCount,
                    $genericName,
                    $expectedCount
                );
            }
        }

        foreach ($this->typeCollector->getAliases() as $alias) {
            if ($this->definition->getTypeAlias($alias) === null) {
                $this->errors[] = sprintf(
                    "Property '%s' on struct '%s' references unknown type alias '%s'",
                    $property->getName(),
                    $struct->getName(),
                    $alias
                );
            }
        }
    }

    public function visitTypeAlias(TypeAlias $alias): void
    {
        $resolved = $alias->getResolvedType();
        if ($resolved === null) {
            return;
        }

        if ($resolved->isReference() && $this->definition->getStruct($resolved->getName() ?? '') === null) {
            $this->errors[] = sprintf(
                "Type alias '%s' references unknown struct '%s'",
                $alias->getName(),
                $resolved->getName() ?? ''
            );
        }
    }
}
