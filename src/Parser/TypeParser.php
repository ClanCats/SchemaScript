<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\Type\TypeNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\Type\StringLiteralTypeNode;

class TypeParser extends SchemaParser
{
    protected ?TypeNode $type = null;

    protected function next(): void
    {
        $this->type = $this->parseType();
    }

    protected function parseType(): TypeNode
    {
        $type = $this->parseSingleType();

        if ($this->parserIsDone() || !$this->currentToken()->isType(T::TOKEN_PIPE)) {
            return $type;
        }

        $types = [$type];
        do {
            $this->skipToken();
            if ($this->parserIsDone()) {
                throw $this->errorParsing("Unexpected trailing pipe in union type.");
            }
            $types[] = $this->parseSingleType();
        } while (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_PIPE));

        return new UnionTypeNode($types);
    }

    protected function parseSingleType(): TypeNode
    {
        $token = $this->currentToken();

        if ($token->isType(T::TOKEN_PAREN_OPEN)) {
            $this->skipToken();
            $type = $this->parseType();
            $this->expectCurrentType(T::TOKEN_PAREN_CLOSE);
            $this->skipToken();
        } elseif ($token->isType(T::TOKEN_SCOPE_OPEN)) {
            $type = $this->parseInlineObject();
        } elseif ($token->isType(T::TOKEN_IDENTIFIER) && $this->nextToken() !== null && $this->nextToken()->isType(T::TOKEN_SCOPE_OPEN)) {
            $explicitName = $token->getValue();
            $this->skipToken();
            $type = $this->parseInlineObject($explicitName);
        } elseif ($token->isType(T::TOKEN_STRING)) {
            $type = new StringLiteralTypeNode($token->getValue());
            $this->skipToken();
        } elseif ($token->isType(T::TOKEN_IDENTIFIER)) {
            $type = new SimpleTypeNode($token->getValue());
            $this->skipToken();
        } else {
            throw $this->errorUnexpectedToken($token);
        }

        while (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_ARRAY_SUFFIX)) {
            $type = new ArrayTypeNode($type);
            $this->skipToken();
        }

        if (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_QUESTION)) {
            $type = new NullableTypeNode($type);
            $this->skipToken();
        }

        return $type;
    }

    private function parseInlineObject(?string $explicitName = null): InlineObjectTypeNode
    {
        $bodyTokens = $this->getTokensUntilClosingScope();
        $bodyParser = new ModelBodyParser($bodyTokens);
        /** @var \ClanCats\SchemaScript\Node\ModelDefinitionNode $bodyNode */
        $bodyNode = $bodyParser->parse();

        $inlineObject = new InlineObjectTypeNode();
        if ($explicitName !== null) {
            $inlineObject->setExplicitName($explicitName);
        }
        foreach ($bodyNode->getProperties() as $property) {
            $inlineObject->addProperty($property);
        }
        foreach ($bodyNode->getMetadata() as $metadata) {
            $inlineObject->addMetadata($metadata);
        }

        return $inlineObject;
    }

    protected function node(): BaseNode
    {
        if ($this->type === null) {
            throw $this->errorParsing("Expected a type expression.");
        }

        return $this->type;
    }
}
