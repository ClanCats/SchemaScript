<?php

namespace ClanCats\SchemaScript\Exception;

class GeneratorException extends \Exception
{
    use HasSourceContext;

    private ?string $contextStructName = null;
    private ?string $contextPropertyName = null;

    public function setStructContext(string $structName, ?string $propertyName = null): self
    {
        $this->contextStructName = $structName;
        $this->contextPropertyName = $propertyName;
        return $this;
    }

    public function getContextStructName(): ?string
    {
        return $this->contextStructName;
    }

    public function getContextPropertyName(): ?string
    {
        return $this->contextPropertyName;
    }
}
