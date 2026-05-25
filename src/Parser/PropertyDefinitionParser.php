<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Node\Type\TypeNode;

class PropertyDefinitionParser extends SchemaParser
{
    protected string $name = '';

    protected bool $isOptional = false;

    protected ?TypeNode $type = null;

    protected function next(): void
    {
        $this->name = $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
        $this->skipToken();

        if (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_QUESTION)) {
            $this->isOptional = true;
            $this->skipToken();
        }

        $this->expectCurrentType(T::TOKEN_COLON);
        $this->skipToken();

        $remainingTokens = $this->getRemainingTokens(true);

        $typeParser = new TypeParser($remainingTokens);
        $typeNode = $typeParser->parse();

        if (!$typeNode instanceof TypeNode) {
            throw $this->errorParsing("Expected a type expression.");
        }

        if ($typeParser->getIndex() < $typeParser->getTokenCount()) {
            $leftover = $typeParser->getTokens()[$typeParser->getIndex()];
            $e = new \ClanCats\SchemaScript\Exception\ParserException(
                sprintf(
                    'Unexpected token "%s" after type expression on line %d, column %d in file %s',
                    $leftover->getValue(),
                    $leftover->getLine(),
                    $leftover->getColumn(),
                    $leftover->getFilename() ?? 'unknown'
                )
            );
            $e->setSourceContext($leftover->getLine(), $leftover->getColumn(), $leftover->getFilename(), null, strlen((string) $leftover->getValue()));
            throw $e;
        }

        $this->type = $typeNode;
    }

    protected function node(): BaseNode
    {
        if ($this->type === null) {
            throw $this->errorParsing("Expected a property definition.");
        }

        $property = new PropertyNode($this->name, $this->type);
        $property->setIsOptional($this->isOptional);

        return $property;
    }
}
