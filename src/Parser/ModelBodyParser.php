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

        if ($token->isType(TokenType::KeywordPrivate)) {
            $this->skipToken();
            $this->skipTokenOfType([TokenType::Line]);
            $this->expectCurrentType(TokenType::Identifier);
            /** @var ModelDefinitionNode $childModel */
            $childModel = $this->parseChild(ModelDefinitionParser::class);
            $childModel->setAnnotations($this->pendingAnnotations);
            $childModel->setPrivate(true);
            $this->pendingAnnotations = [];
            $this->pendingComments = [];
            $this->model->addChildModel($childModel);
            return;
        }

        if ($token->isType(TokenType::Identifier)) {
            if ($this->isChildModelStart()) {
                /** @var ModelDefinitionNode $childModel */
                $childModel = $this->parseChild(ModelDefinitionParser::class);
                $childModel->setAnnotations($this->pendingAnnotations);
                $this->pendingAnnotations = [];
                $this->pendingComments = [];
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

    private function isChildModelStart(): bool
    {
        $i = 1;
        $next = $this->nextToken($i);
        if ($next === null) {
            return false;
        }

        if ($next->isType(TokenType::ScopeOpen)) {
            return true;
        }

        if ($next->isType(TokenType::AngleOpen)) {
            $depth = 1;
            $i++;
            while (($peek = $this->nextToken($i)) !== null && $depth > 0) {
                if ($peek->isType(TokenType::AngleOpen)) {
                    $depth++;
                } elseif ($peek->isType(TokenType::AngleClose)) {
                    $depth--;
                }
                $i++;
            }
            $after = $this->nextToken($i);
            return $after !== null && ($after->isType(TokenType::ScopeOpen) || $after->isType(TokenType::Colon));
        }

        return false;
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
