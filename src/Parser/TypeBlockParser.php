<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
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
        $this->expectCurrentType(T::TOKEN_METADATA_KEY);
        $this->skipToken();

        $this->expectCurrentType(T::TOKEN_EQUAL);
        $this->skipToken();

        $this->expectCurrentType(T::TOKEN_SCOPE_OPEN);
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

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->isType(T::TOKEN_LINE)) {
                $i++;
                continue;
            }

            if ($token->isType(T::TOKEN_ANNOTATION)) {
                $annotationTokens = [$token];
                $i++;

                if ($i < $count && $tokens[$i]->isType(T::TOKEN_PAREN_OPEN)) {
                    while ($i < $count) {
                        $annotationTokens[] = $tokens[$i];
                        if ($tokens[$i]->isType(T::TOKEN_PAREN_CLOSE)) {
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

            if ($token->isType(T::TOKEN_IDENTIFIER)) {
                $name = $token->getValue();
                $i++;

                // skip newlines between identifier and potential =
                $peekIndex = $i;
                while ($peekIndex < $count && $tokens[$peekIndex]->isType(T::TOKEN_LINE)) {
                    $peekIndex++;
                }

                if ($peekIndex < $count && $tokens[$peekIndex]->isType(T::TOKEN_EQUAL)) {
                    $i = $peekIndex + 1; // skip past =

                    // skip newlines after =
                    while ($i < $count && $tokens[$i]->isType(T::TOKEN_LINE)) {
                        $i++;
                    }

                    // collect RHS tokens until newline (respecting brace depth)
                    $rhsTokens = [];
                    $braceDepth = 0;
                    while ($i < $count) {
                        $t = $tokens[$i];
                        if ($t->isType(T::TOKEN_LINE) && $braceDepth === 0) {
                            $i++;
                            break;
                        }
                        if ($t->isType(T::TOKEN_SCOPE_OPEN)) {
                            $braceDepth++;
                        } elseif ($t->isType(T::TOKEN_SCOPE_CLOSE)) {
                            $braceDepth--;
                        }
                        $rhsTokens[] = $t;
                        $i++;
                    }

                    $typeParser = new TypeParser($rhsTokens);
                    /** @var \ClanCats\SchemaScript\Node\Type\TypeNode $typeNode */
                    $typeNode = $typeParser->parse();

                    $alias = new TypeAliasNode($name);
                    $alias->setTypeDefinition($typeNode);
                    $alias->setAnnotations($pendingAnnotations);
                    $pendingAnnotations = [];
                    $this->aliases[] = $alias;
                    continue;
                }

                // bare declaration (no = follows)
                $alias = new TypeAliasNode($name);
                $alias->setAnnotations($pendingAnnotations);
                $pendingAnnotations = [];
                $this->aliases[] = $alias;
                continue;
            }

            $e = new \ClanCats\SchemaScript\Exception\ParserException(
                sprintf('Unexpected token "%s" in type block on line %d, column %d in file %s', $token->getValue(), $token->getLine(), $token->getColumn(), $token->getFilename() ?? 'unknown')
            );
            $e->setSourceContext($token->getLine(), $token->getColumn(), $token->getFilename(), null, strlen((string) $token->getValue()));
            throw $e;
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
