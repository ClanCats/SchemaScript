<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\ConstantNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;

class NamespaceParser extends SchemaParser
{
    protected ?NamespaceNode $namespace = null;

    protected function next(): void
    {
        if ($this->currentToken()->isType(T::TOKEN_LINE)) {
            $this->skipToken();
            return;
        }

        $this->expectCurrentType(T::TOKEN_KEYWORD_NS);
        $this->skipToken();

        $name = $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
        $this->skipToken();

        $this->expectCurrentType(T::TOKEN_SCOPE_OPEN);
        $bodyTokens = $this->getTokensUntilClosingScope();

        $this->namespace = new NamespaceNode($name);
        $this->parseBody($bodyTokens);
        $this->finish();
    }

    /**
     * @param array<T> $tokens
     */
    protected function parseBody(array $tokens): void
    {
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->isType(T::TOKEN_LINE)) {
                $i++;
                continue;
            }

            if ($token->isType(T::TOKEN_KEYWORD_CONST)) {
                $i++;
                if ($i >= $count) {
                    throw $this->errorParsing("Expected identifier after 'const'.");
                }

                $nameToken = $tokens[$i];
                if (!$nameToken->isType(T::TOKEN_IDENTIFIER)) {
                    throw $this->errorUnexpectedToken($nameToken);
                }

                $constant = new ConstantNode($nameToken->getValue());
                $i++;

                if ($i < $count && $tokens[$i]->isType(T::TOKEN_EQUAL)) {
                    $i++;
                    if ($i >= $count) {
                        throw $this->errorParsing("Expected value after '='.");
                    }

                    $valueToken = $tokens[$i];

                    if ($valueToken->isType(T::TOKEN_STRING) || $valueToken->isType(T::TOKEN_NUMBER)) {
                        $constant->setValue(ValueNode::fromToken($valueToken));
                        $i++;
                    } elseif ($valueToken->isType(T::TOKEN_IDENTIFIER)) {
                        $identifier = $valueToken->getValue();
                        $i++;

                        if ($i < $count && $tokens[$i]->isType(T::TOKEN_DOUBLE_COLON)) {
                            $i++;
                            if ($i >= $count || !$tokens[$i]->isType(T::TOKEN_IDENTIFIER)) {
                                throw $this->errorParsing("Expected identifier after '::'.");
                            }
                            $constant->setValue(new ReferenceNode($identifier, $tokens[$i]->getValue()));
                            $i++;
                        } else {
                            $constant->setValue(new ValueNode(ValueNode::TYPE_IDENTIFIER, $identifier));
                        }
                    } else {
                        throw $this->errorUnexpectedToken($valueToken);
                    }
                }

                $this->namespace?->addConstant($constant);
                continue;
            }

            throw $this->errorUnexpectedToken($token);
        }
    }

    protected function node(): BaseNode
    {
        if ($this->namespace === null) {
            throw $this->errorParsing("Expected a namespace definition.");
        }

        return $this->namespace;
    }
}
