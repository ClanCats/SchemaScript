<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\TypeAliasNode;
use ClanCats\SchemaScript\Node\AnnotationNode;
use ClanCats\SchemaScript\Node\ScopeNode;

class TypeBlockParser extends SchemaParser
{
    /**
     * @var array<TypeAliasNode>
     */
    protected array $aliases = [];

    protected function next(): void
    {
        $this->expectCurrentType(TokenType::MetadataKey);
        $this->skipToken();

        if ($this->currentToken()->isType(TokenType::Equal)) {
            $this->skipToken();
        }

        $this->expectCurrentType(TokenType::ScopeOpen);
        $bodyTokens = $this->getTokensUntilClosingScope();

        $this->parseBody($bodyTokens);
        $this->finish();
    }

    /**
     * @param array<T> $tokens
     */
    protected function parseBody(array $tokens): void
    {
        /** @var array<AnnotationNode> $pendingAnnotations */
        $pendingAnnotations = [];
        $i = 0;
        $count = count($tokens);
        $isPublic = false;

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->isType(TokenType::Line) || $token->isType(TokenType::Comment)) {
                $i++;
                continue;
            }

            if ($token->isType(TokenType::Annotation)) {
                $annotationTokens = [$token];
                $i++;

                if ($i < $count && $tokens[$i]->isType(TokenType::ParenOpen)) {
                    while ($i < $count) {
                        $annotationTokens[] = $tokens[$i];
                        if ($tokens[$i]->isType(TokenType::ParenClose)) {
                            $i++;
                            break;
                        }
                        $i++;
                    }
                }

                $parser = new AnnotationParser($annotationTokens);
                /** @var AnnotationNode $annotation */
                $annotation = $parser->parse();
                $pendingAnnotations[] = $annotation;
                continue;
            }

            if ($token->isType(TokenType::KeywordPub)) {
                $isPublic = true;
                $i++;
                while ($i < $count && $tokens[$i]->isType(TokenType::Line)) {
                    $i++;
                }
                if ($i >= $count || !$tokens[$i]->isType(TokenType::Identifier)) {
                    throw $this->errorParsingAt('Expected type alias name after "pub"', $token);
                }
                $token = $tokens[$i];
            }

            if ($token->isType(TokenType::Identifier)) {
                $name = $token->getValue();
                $i++;

                // skip newlines between identifier and potential =
                $peekIndex = $i;
                while ($peekIndex < $count && $tokens[$peekIndex]->isType(TokenType::Line)) {
                    $peekIndex++;
                }

                if ($peekIndex < $count && $tokens[$peekIndex]->isType(TokenType::Equal)) {
                    $i = $peekIndex + 1; // skip past =

                    // skip newlines after =
                    while ($i < $count && $tokens[$i]->isType(TokenType::Line)) {
                        $i++;
                    }

                    // collect RHS tokens until newline (respecting brace depth)
                    $rhsTokens = [];
                    $braceDepth = 0;
                    while ($i < $count) {
                        $t = $tokens[$i];
                        if ($t->isType(TokenType::Line) && $braceDepth === 0) {
                            $i++;
                            break;
                        }
                        if ($t->isType(TokenType::ScopeOpen)) {
                            $braceDepth++;
                        } elseif ($t->isType(TokenType::ScopeClose)) {
                            $braceDepth--;
                        }
                        $rhsTokens[] = $t;
                        $i++;
                    }

                    $typeParser = new TypeParser($rhsTokens);
                    /** @var \ClanCats\SchemaScript\Node\Type\TypeNode $typeNode */
                    $typeNode = $typeParser->parse();

                    $alias = new TypeAliasNode($name);
                    $alias->setSourcePosition($token->getLine(), $token->getColumn(), $token->getFilename());
                    $alias->setTypeDefinition($typeNode);
                    $alias->setAnnotations($pendingAnnotations);
                    $alias->setIsPublic($isPublic);
                    $isPublic = false;
                    $pendingAnnotations = [];
                    $this->aliases[] = $alias;
                    continue;
                }

                // detect unknown keywords on the same line
                // (e.g., "bla MessageType = ..." where "bla" is not a valid keyword)
                if ($i < $count && $tokens[$i]->isType(TokenType::Identifier)) {
                    throw $this->errorParsingAt(
                        sprintf('Unknown keyword "%s" in type block', $name),
                        $token
                    );
                }

                // bare declaration (no = follows)
                $alias = new TypeAliasNode($name);
                $alias->setSourcePosition($token->getLine(), $token->getColumn(), $token->getFilename());
                $alias->setAnnotations($pendingAnnotations);
                $alias->setIsPublic($isPublic);
                $pendingAnnotations = [];
                $this->aliases[] = $alias;
                continue;
            }

            throw $this->errorParsingAt(
                sprintf('Unexpected token "%s" in type block', $token->getValue()),
                $token
            );
        }
    }

    protected function node(): BaseNode
    {
        $scope = new ScopeNode();
        foreach ($this->aliases as $alias) {
            $scope->addTypeAlias($alias);
        }
        return $scope;
    }
}
