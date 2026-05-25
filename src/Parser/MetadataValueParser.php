<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\MetadataBlockNode;
use ClanCats\SchemaScript\Node\MetadataListNode;
use ClanCats\SchemaScript\Node\AnnotationNode;

class MetadataValueParser extends SchemaParser
{
    protected ?BaseNode $result = null;

    protected function next(): void
    {
        $token = $this->currentToken();

        if ($token->isType(TokenType::String) || $token->isType(TokenType::Number)) {
            $this->result = ValueNode::fromToken($token);
            $this->skipToken();
            $this->finish();
            return;
        }

        if ($token->isType(TokenType::Identifier)) {
            $this->result = $this->parseIdentifierValue();
            $this->finish();
            return;
        }

        if ($token->isType(TokenType::ScopeOpen)) {
            $this->result = $this->parseScopeValue();
            $this->finish();
            return;
        }

        throw $this->errorUnexpectedToken($token);
    }

    private function parseIdentifierValue(): BaseNode
    {
        $identifier = $this->currentToken()->getValue();
        $this->skipToken();

        if ($identifier === 'true' || $identifier === 'false') {
            return new ValueNode(ValueNode::TYPE_BOOLEAN, $identifier === 'true');
        }

        $parts = $this->parseDoubleColonSeparatedIdentifiers($identifier);
        if (count($parts) >= 2) {
            return new ReferenceNode(...$parts);
        }

        return new ValueNode(ValueNode::TYPE_IDENTIFIER, $identifier);
    }

    private function parseScopeValue(): BaseNode
    {
        $bodyTokens = $this->getTokensUntilClosingScope();

        if (empty($bodyTokens)) {
            return new MetadataBlockNode();
        }

        if ($this->isListBody($bodyTokens)) {
            return $this->parseListBody($bodyTokens);
        }

        return $this->parseBlockBody($bodyTokens);
    }

    /**
     * @param array<T> $tokens
     */
    private function isListBody(array $tokens): bool
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->isType(TokenType::Line) || $token->isType(TokenType::Comment) || $token->isType(TokenType::Space)) {
                continue;
            }

            if ($token->isType(TokenType::MetadataKey)) {
                return false;
            }
            if ($token->isType(TokenType::Annotation)) {
                return false;
            }
            if ($token->isType(TokenType::Identifier)) {
                $nextIdx = $this->findNextContentToken($tokens, $i + 1);
                if ($nextIdx !== null && $tokens[$nextIdx]->isType(TokenType::Equal)) {
                    return false;
                }
                if ($nextIdx === null || $tokens[$nextIdx]->isType(TokenType::Line) || $tokens[$nextIdx]->isType(TokenType::ScopeClose)) {
                    return false;
                }
            }

            return true;
        }

        return true;
    }

    /**
     * @param array<T> $tokens
     */
    private function findNextContentToken(array $tokens, int $startIdx): ?int
    {
        $count = count($tokens);
        for ($i = $startIdx; $i < $count; $i++) {
            if (!$tokens[$i]->isType(TokenType::Line) && !$tokens[$i]->isType(TokenType::Comment) && !$tokens[$i]->isType(TokenType::Space)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @param array<T> $tokens
     */
    private function parseListBody(array $tokens): MetadataListNode
    {
        $list = new MetadataListNode();
        $parser = new self($tokens);

        while (!$parser->parserIsDone()) {
            $token = $parser->currentToken();

            if ($token->isType(TokenType::Comma) || $token->isType(TokenType::Line) || $token->isType(TokenType::Comment)) {
                $parser->skipToken();
                continue;
            }

            /** @var BaseNode $value */
            $value = $parser->parseChild(self::class);
            $list->addItem($value);
        }

        return $list;
    }

    /**
     * @param array<T> $tokens
     */
    private function parseBlockBody(array $tokens): MetadataBlockNode
    {
        $block = new MetadataBlockNode();
        $parser = new self($tokens);
        /** @var array<AnnotationNode> $pendingAnnotations */
        $pendingAnnotations = [];

        while (!$parser->parserIsDone()) {
            $token = $parser->currentToken();

            if ($token->isType(TokenType::Line) || $token->isType(TokenType::Comment)) {
                $parser->skipToken();
                continue;
            }

            if ($token->isType(TokenType::Annotation)) {
                /** @var AnnotationNode $annotation */
                $annotation = $parser->parseChild(AnnotationParser::class);
                $pendingAnnotations[] = $annotation;
                continue;
            }

            if ($token->isType(TokenType::MetadataKey)) {
                $key = $token->getValue();
                $parser->skipToken();

                $parser->expectCurrentType(TokenType::Equal);
                $parser->skipToken();

                /** @var BaseNode $value */
                $value = $parser->parseChild(self::class);
                $entry = new MetadataEntryNode($key, $value, $pendingAnnotations);
                $pendingAnnotations = [];
                $block->addEntry($entry);
                continue;
            }

            if ($token->isType(TokenType::Identifier)) {
                $key = $token->getValue();
                $parser->skipToken();

                if (!$parser->parserIsDone() && $parser->currentToken()->isType(TokenType::Equal)) {
                    $parser->skipToken();
                    /** @var BaseNode $value */
                    $value = $parser->parseChild(self::class);
                    $entry = new MetadataEntryNode($key, $value, $pendingAnnotations);
                    $pendingAnnotations = [];
                    $block->addEntry($entry);
                    continue;
                }

                $entry = new MetadataEntryNode($key, null, $pendingAnnotations);
                $pendingAnnotations = [];
                $block->addEntry($entry);
                continue;
            }

            throw $parser->errorUnexpectedToken($token);
        }

        return $block;
    }

    protected function node(): BaseNode
    {
        if ($this->result === null) {
            throw $this->errorParsing("Expected a metadata value.");
        }

        return $this->result;
    }
}
