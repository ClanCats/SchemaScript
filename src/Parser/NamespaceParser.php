<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\ConstantNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;

class NamespaceParser extends SchemaParser
{
    protected ?NamespaceNode $namespace = null;

    protected function next(): void
    {
        if ($this->currentToken()->isType(TokenType::Line) || $this->currentToken()->isType(TokenType::Comment)) {
            $this->skipToken();
            return;
        }

        $this->expectCurrentType(TokenType::KeywordNs);
        $this->skipToken();

        $nameToken = $this->expectCurrentType(TokenType::Identifier);
        $name = $nameToken->getValue();
        $this->skipToken();

        $this->expectCurrentType(TokenType::ScopeOpen);
        $bodyTokens = $this->getTokensUntilClosingScope();

        $namespace = new NamespaceNode($name);
        $this->capturePosition($namespace, $nameToken);
        $this->parseBody($namespace, $bodyTokens);
        $this->namespace = $namespace;
        $this->finish();
    }

    /**
     * @param array<T> $tokens
     */
    protected function parseBody(NamespaceNode $target, array $tokens): void
    {
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->isType(TokenType::Line) || $token->isType(TokenType::Comment)) {
                $i++;
                continue;
            }

            if ($token->isType(TokenType::KeywordNs)) {
                $i++;
                if ($i >= $count || !$tokens[$i]->isType(TokenType::Identifier)) {
                    throw $this->errorParsing("Expected identifier after 'ns'.");
                }
                $childNameToken = $tokens[$i];
                $childName = $childNameToken->getValue();
                $i++;

                if ($i >= $count || !$tokens[$i]->isType(TokenType::ScopeOpen)) {
                    throw $this->errorParsing("Expected '{' after namespace name.");
                }
                $i++;

                $depth = 1;
                $childBodyTokens = [];
                while ($i < $count) {
                    if ($tokens[$i]->isType(TokenType::ScopeOpen)) {
                        $depth++;
                    } elseif ($tokens[$i]->isType(TokenType::ScopeClose)) {
                        $depth--;
                        if ($depth === 0) {
                            $i++;
                            break;
                        }
                    }
                    $childBodyTokens[] = $tokens[$i];
                    $i++;
                }

                $childNode = new NamespaceNode($childName);
                $childNode->setSourcePosition($childNameToken->getLine(), $childNameToken->getColumn(), $childNameToken->getFilename());
                $this->parseBody($childNode, $childBodyTokens);
                $target->addChild($childNode);
                continue;
            }

            if ($token->isType(TokenType::KeywordConst)) {
                $i++;
                if ($i >= $count) {
                    throw $this->errorParsing("Expected identifier after 'const'.");
                }

                $nameToken = $tokens[$i];
                if (!$nameToken->isType(TokenType::Identifier)) {
                    throw $this->errorUnexpectedToken($nameToken);
                }

                $constant = new ConstantNode($nameToken->getValue());
                $constant->setSourcePosition($nameToken->getLine(), $nameToken->getColumn(), $nameToken->getFilename());
                $i++;

                if ($i < $count && $tokens[$i]->isType(TokenType::Equal)) {
                    $i++;
                    if ($i >= $count) {
                        throw $this->errorParsing("Expected value after '='.");
                    }

                    $valueToken = $tokens[$i];

                    if ($valueToken->isType(TokenType::String) || $valueToken->isType(TokenType::Number)) {
                        $constant->setValue(ValueNode::fromToken($valueToken));
                        $i++;
                    } elseif ($valueToken->isType(TokenType::Identifier)) {
                        $parts = [$valueToken->getValue()];
                        $i++;

                        while ($i < $count && $tokens[$i]->isType(TokenType::DoubleColon)) {
                            $i++;
                            if ($i >= $count || !$tokens[$i]->isType(TokenType::Identifier)) {
                                throw $this->errorParsing("Expected identifier after '::'.");
                            }
                            $parts[] = $tokens[$i]->getValue();
                            $i++;
                        }

                        if (count($parts) >= 2) {
                            $constant->setValue(new ReferenceNode(...$parts));
                        } else {
                            $constant->setValue(new ValueNode(ValueNode::TYPE_IDENTIFIER, $parts[0]));
                        }
                    } else {
                        throw $this->errorUnexpectedToken($valueToken);
                    }
                }

                $target->addConstant($constant);
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
