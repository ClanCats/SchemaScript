<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\Type\TypeNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\Type\StringLiteralTypeNode;
use ClanCats\SchemaScript\Node\Type\GenericTypeNode;

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

        if ($this->parserIsDone() || !$this->currentToken()->isType(TokenType::Pipe)) {
            return $type;
        }

        $types = [$type];
        do {
            $this->skipToken();
            if ($this->parserIsDone()) {
                throw $this->errorParsing("Unexpected trailing pipe in union type.");
            }
            $types[] = $this->parseSingleType();
        } while (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::Pipe));

        return new UnionTypeNode($types);
    }

    protected function parseSingleType(): TypeNode
    {
        $token = $this->currentToken();

        if ($token->isType(TokenType::ParenOpen)) {
            $this->skipToken();
            $type = $this->parseType();
            $this->expectCurrentType(TokenType::ParenClose);
            $this->skipToken();
        } elseif ($token->isType(TokenType::ScopeOpen)) {
            $type = $this->parseInlineObject();
        } elseif ($token->isType(TokenType::Identifier) && $this->nextToken() !== null && $this->nextToken()->isType(TokenType::ScopeOpen)) {
            $explicitName = $token->getValue();
            $this->skipToken();
            $type = $this->parseInlineObject($explicitName);
        } elseif ($token->isType(TokenType::String)) {
            $type = new StringLiteralTypeNode($token->getValue());
            $this->capturePosition($type, $token);
            $this->skipToken();
        } elseif ($token->isType(TokenType::Identifier) && $this->nextToken() !== null && $this->nextToken()->isType(TokenType::AngleOpen)) {
            $name = $token->getValue();
            $this->skipToken(); // skip identifier
            $this->skipToken(); // skip <
            $arguments = [];
            while (true) {
                $this->skipTokenOfType([TokenType::Space, TokenType::Line]);
                $arguments[] = $this->parseType();
                $this->skipTokenOfType([TokenType::Space, TokenType::Line]);
                if ($this->currentToken()->isType(TokenType::AngleClose)) {
                    $this->skipToken();
                    break;
                }
                $this->expectCurrentType(TokenType::Comma);
                $this->skipToken();
            }
            $type = new GenericTypeNode($name, $arguments);
            $this->capturePosition($type, $token);
        } elseif ($token->isType(TokenType::Identifier)) {
            $type = new SimpleTypeNode($token->getValue());
            $this->capturePosition($type, $token);
            $this->skipToken();
        } else {
            throw $this->errorUnexpectedToken($token);
        }

        while (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::ArraySuffix)) {
            $type = new ArrayTypeNode($type);
            $this->skipToken();
        }

        if (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::Question)) {
            $type = new NullableTypeNode($type);
            $this->skipToken();
        }

        return $type;
    }

    private function parseInlineObject(?string $explicitName = null): InlineObjectTypeNode
    {
        $openToken = $this->currentToken();
        $bodyTokens = $this->getTokensUntilClosingScope();
        $bodyParser = new ModelBodyParser($bodyTokens);
        /** @var \ClanCats\SchemaScript\Node\ModelDefinitionNode $bodyNode */
        $bodyNode = $bodyParser->parse();

        $inlineObject = new InlineObjectTypeNode();
        $this->capturePosition($inlineObject, $openToken);
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
