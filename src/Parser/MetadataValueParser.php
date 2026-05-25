<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
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

        if ($token->isType(T::TOKEN_STRING) || $token->isType(T::TOKEN_NUMBER)) {
            $this->result = ValueNode::fromToken($token);
            $this->skipToken();
            $this->finish();
            return;
        }

        if ($token->isType(T::TOKEN_IDENTIFIER)) {
            $this->result = $this->parseIdentifierValue();
            $this->finish();
            return;
        }

        if ($token->isType(T::TOKEN_SCOPE_OPEN)) {
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

        if (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_DOUBLE_COLON)) {
            $parts = [$identifier];
            while (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_DOUBLE_COLON)) {
                $this->skipToken();
                $parts[] = $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
                $this->skipToken();
            }
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

            if ($token->isType(T::TOKEN_LINE) || $token->isType(T::TOKEN_COMMENT) || $token->isType(T::TOKEN_SPACE)) {
                continue;
            }

            if ($token->isType(T::TOKEN_METADATA_KEY)) {
                return false;
            }
            if ($token->isType(T::TOKEN_ANNOTATION)) {
                return false;
            }
            if ($token->isType(T::TOKEN_IDENTIFIER)) {
                $nextIdx = $this->findNextContentToken($tokens, $i + 1);
                if ($nextIdx !== null && $tokens[$nextIdx]->isType(T::TOKEN_EQUAL)) {
                    return false;
                }
                if ($nextIdx === null || $tokens[$nextIdx]->isType(T::TOKEN_LINE) || $tokens[$nextIdx]->isType(T::TOKEN_SCOPE_CLOSE)) {
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
            if (!$tokens[$i]->isType(T::TOKEN_LINE) && !$tokens[$i]->isType(T::TOKEN_COMMENT) && !$tokens[$i]->isType(T::TOKEN_SPACE)) {
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

            if ($token->isType(T::TOKEN_COMMA) || $token->isType(T::TOKEN_LINE) || $token->isType(T::TOKEN_COMMENT)) {
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

            if ($token->isType(T::TOKEN_LINE) || $token->isType(T::TOKEN_COMMENT)) {
                $parser->skipToken();
                continue;
            }

            if ($token->isType(T::TOKEN_ANNOTATION)) {
                /** @var AnnotationNode $annotation */
                $annotation = $parser->parseChild(AnnotationParser::class);
                $pendingAnnotations[] = $annotation;
                continue;
            }

            if ($token->isType(T::TOKEN_METADATA_KEY)) {
                $key = $token->getValue();
                $parser->skipToken();

                $parser->expectCurrentType(T::TOKEN_EQUAL);
                $parser->skipToken();

                /** @var BaseNode $value */
                $value = $parser->parseChild(self::class);
                $entry = new MetadataEntryNode($key, $value, $pendingAnnotations);
                $pendingAnnotations = [];
                $block->addEntry($entry);
                continue;
            }

            if ($token->isType(T::TOKEN_IDENTIFIER)) {
                $key = $token->getValue();
                $parser->skipToken();

                if (!$parser->parserIsDone() && $parser->currentToken()->isType(T::TOKEN_EQUAL)) {
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
