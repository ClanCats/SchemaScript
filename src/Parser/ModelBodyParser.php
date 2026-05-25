<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Node\CommentNode;
use ClanCats\SchemaScript\Node\AnnotationNode;

class ModelBodyParser extends SchemaParser
{
    protected ModelDefinitionNode $model;

    /**
     * @var array<AnnotationNode>
     */
    protected array $pendingAnnotations = [];

    /**
     * @var array<string>
     */
    protected array $pendingComments = [];

    protected function prepare(): void
    {
        $this->model = new ModelDefinitionNode();
    }

    protected function next(): void
    {
        $token = $this->currentToken();

        if ($token->isType(TokenType::Comment)) {
            $raw = $token->getValue();
            $text = preg_replace('/^\/\/\s?/', '', $raw);
            $this->pendingComments[] = $text;
            $this->skipToken();
            return;
        }

        if ($token->isType(TokenType::Line)) {
            $this->skipToken();
            return;
        }

        if ($token->isType(TokenType::Annotation)) {
            /** @var AnnotationNode $annotation */
            $annotation = $this->parseChild(AnnotationParser::class);
            $this->pendingAnnotations[] = $annotation;
            return;
        }

        if ($token->isType(TokenType::MetadataKey)) {
            $this->pendingComments = [];
            if (!empty($this->pendingAnnotations)) {
                $names = array_map(fn(AnnotationNode $a) => '@' . $a->getName(), $this->pendingAnnotations);
                throw $this->errorParsing(sprintf(
                    'Annotation(s) %s cannot be applied to metadata',
                    implode(', ', $names)
                ));
            }

            $key = $token->getValue();
            if ($key === 'type') {
                /** @var \ClanCats\SchemaScript\Node\ScopeNode $typesScope */
                $typesScope = $this->parseChild(TypeBlockParser::class);
                foreach ($typesScope->getTypeAliases() as $alias) {
                    $this->model->addTypeAlias($alias);
                }
                return;
            }

            /** @var MetadataEntryNode $metadata */
            $metadata = $this->parseChild(MetadataParser::class);
            $this->model->addMetadata($metadata);
            return;
        }

        if ($token->isType(TokenType::Identifier)) {
            // Disambiguate: property (name: type) vs child model (Name { ... })
            $next = $this->nextToken();
            if ($next !== null && $next->isType(TokenType::ScopeOpen)) {
                $this->pendingComments = [];
                /** @var ModelDefinitionNode $childModel */
                $childModel = $this->parseChild(ModelDefinitionParser::class);
                $this->model->addChildModel($childModel);
                return;
            }

            $propertyTokens = $this->getPropertyTokens();
            $parser = new PropertyDefinitionParser($propertyTokens);
            /** @var PropertyNode $property */
            $property = $parser->parse();
            $property->setAnnotations($this->pendingAnnotations);
            $this->pendingAnnotations = [];
            if (!empty($this->pendingComments)) {
                $property->setComment(new CommentNode($this->pendingComments));
                $this->pendingComments = [];
            }
            $this->model->addProperty($property);
            return;
        }

        throw $this->errorUnexpectedToken($token);
    }

    /**
     * @return array<T>
     */
    protected function getPropertyTokens(): array
    {
        $tokens = [];

        while (!$this->parserIsDone()) {
            $token = $this->currentToken();

            if ($token->isType(TokenType::Line)) {
                $this->skipToken();
                break;
            }

            if ($token->isType(TokenType::ScopeOpen)) {
                $tokens[] = $token;
                $this->skipToken();
                $depth = 1;

                while (!$this->parserIsDone() && $depth > 0) {
                    $inner = $this->currentToken();
                    $tokens[] = $inner;
                    if ($inner->isType(TokenType::ScopeOpen)) {
                        $depth++;
                    } elseif ($inner->isType(TokenType::ScopeClose)) {
                        $depth--;
                    }
                    $this->skipToken();
                }
                continue;
            }

            $tokens[] = $token;
            $this->skipToken();
        }

        return $tokens;
    }

    protected function node(): BaseNode
    {
        if (!empty($this->pendingAnnotations)) {
            $names = array_map(fn(AnnotationNode $a) => $a->getName(), $this->pendingAnnotations);
            throw $this->errorParsing(sprintf(
                'Orphaned annotation(s) %s not attached to any property',
                implode(', ', $names)
            ));
        }

        return $this->model;
    }
}
