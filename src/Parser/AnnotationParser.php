<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
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

    protected function next(): void
    {
        $token = $this->currentToken();

        if ($token->isType(T::TOKEN_ANNOTATION)) {
            $this->name = substr($token->getValue(), 1);
            $this->skipToken();

            if (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_PAREN_OPEN)) {
                $this->skipToken();
                $this->parseArguments();
            }

            $this->finish();
            return;
        }

        if ($token->isType(T::TOKEN_LINE)) {
            $this->skipToken();
            return;
        }

        throw $this->errorUnexpectedToken($token);
    }

    protected function parseArguments(): void
    {
        while (!$this->parserIsDone() && !$this->currentToken()->isType(T::TOKEN_PAREN_CLOSE)) {
            $token = $this->currentToken();

            if ($token->isType(T::TOKEN_COMMA)) {
                $this->skipToken();
                continue;
            }

            if ($token->isType(T::TOKEN_STRING) || $token->isType(T::TOKEN_NUMBER)) {
                $this->arguments[] = ValueNode::fromToken($token);
                $this->skipToken();
                continue;
            }

            if ($token->isType(T::TOKEN_IDENTIFIER)) {
                $identifier = $token->getValue();
                $this->skipToken();

                if (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_DOUBLE_COLON)) {
                    $parts = [$identifier];
                    while (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_DOUBLE_COLON)) {
                        $this->skipToken();
                        $parts[] = $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
                        $this->skipToken();
                    }
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
        return new AnnotationNode($this->name, $this->arguments);
    }
}
