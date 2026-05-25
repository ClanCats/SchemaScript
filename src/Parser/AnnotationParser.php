<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\AnnotationNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;

class AnnotationParser extends SchemaParser
{
    protected string $name = '';

    /**
     * @var array<BaseNode>
     */
    protected array $arguments = [];

    protected ?T $leadingToken = null;

    protected function next(): void
    {
        $token = $this->currentToken();

        if ($token->isType(TokenType::Annotation)) {
            $this->leadingToken = $token;
            $this->name = substr($token->getValue(), 1);
            $this->skipToken();

            if (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::ParenOpen)) {
                $this->skipToken();
                $this->parseArguments();
            }

            $this->finish();
            return;
        }

        if ($token->isType(TokenType::Line)) {
            $this->skipToken();
            return;
        }

        throw $this->errorUnexpectedToken($token);
    }

    protected function parseArguments(): void
    {
        while (!$this->parserIsDone() && !$this->currentToken()->isType(TokenType::ParenClose)) {
            $token = $this->currentToken();

            if ($token->isType(TokenType::Comma)) {
                $this->skipToken();
                continue;
            }

            if ($token->isType(TokenType::String) || $token->isType(TokenType::Number)) {
                $this->arguments[] = ValueNode::fromToken($token);
                $this->skipToken();
                continue;
            }

            if ($token->isType(TokenType::Identifier)) {
                $identifier = $token->getValue();
                $this->skipToken();

                $parts = $this->parseDoubleColonSeparatedIdentifiers($identifier);
                if (count($parts) >= 2) {
                    $this->arguments[] = new ReferenceNode(...$parts);
                } else {
                    $this->arguments[] = new ValueNode(ValueNode::TYPE_IDENTIFIER, $identifier);
                }
                continue;
            }

            throw $this->errorUnexpectedToken($token);
        }

        if ($this->parserIsDone()) {
            throw $this->errorParsing("Unclosed annotation parentheses");
        }

        $this->skipToken();
    }

    protected function node(): BaseNode
    {
        $node = new AnnotationNode($this->name, $this->arguments);
        if ($this->leadingToken !== null) {
            $this->capturePosition($node, $this->leadingToken);
        }
        return $node;
    }
}
