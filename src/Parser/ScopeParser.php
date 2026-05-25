<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\ImportNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\ConstantNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;

class ScopeParser extends SchemaParser
{
    protected ScopeNode $scope;

    protected function prepare(): void
    {
        $this->scope = new ScopeNode();
    }

    protected function next(): void
    {
        $token = $this->currentToken();

        if ($token->isType(TokenType::Line) || $token->isType(TokenType::Comment)) {
            $this->skipToken();
            return;
        }

        if ($token->isType(TokenType::KeywordImport)) {
            /** @var ImportNode $import */
            $import = $this->parseChild(ImportParser::class);
            $this->scope->addImport($import);
            return;
        }

        if ($token->isType(TokenType::MetadataKey)) {
            $key = $token->getValue();

            if ($key === 'type') {
                /** @var ScopeNode $typesScope */
                $typesScope = $this->parseChild(TypeBlockParser::class);
                foreach ($typesScope->getTypeAliases() as $alias) {
                    $this->scope->addTypeAlias($alias);
                }
                return;
            }

            /** @var MetadataEntryNode $metadata */
            $metadata = $this->parseChild(MetadataParser::class);
            $this->scope->addMetadata($metadata);
            return;
        }

        if ($token->isType(TokenType::KeywordNs)) {
            /** @var NamespaceNode $namespace */
            $namespace = $this->parseChild(NamespaceParser::class);
            $this->scope->addNamespace($namespace);
            return;
        }

        if ($token->isType(TokenType::KeywordConst)) {
            $this->skipToken();

            $name = $this->expectCurrentType(TokenType::Identifier)->getValue();
            $this->skipToken();

            $constant = new ConstantNode($name);

            if (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::Equal)) {
                $this->skipToken();
                $valueToken = $this->currentToken();

                if ($valueToken->isType(TokenType::String) || $valueToken->isType(TokenType::Number)) {
                    $constant->setValue(ValueNode::fromToken($valueToken));
                    $this->skipToken();
                } elseif ($valueToken->isType(TokenType::Identifier)) {
                    $identifier = $valueToken->getValue();
                    $this->skipToken();

                    $parts = $this->parseDoubleColonSeparatedIdentifiers($identifier);
                    if (count($parts) >= 2) {
                        $constant->setValue(new ReferenceNode(...$parts));
                    } else {
                        $constant->setValue(new ValueNode(ValueNode::TYPE_IDENTIFIER, $identifier));
                    }
                } else {
                    throw $this->errorUnexpectedToken($valueToken);
                }
            }

            $this->scope->addConstant($constant);
            return;
        }

        if ($token->isType(TokenType::Identifier)) {
            /** @var ModelDefinitionNode $model */
            $model = $this->parseChild(ModelDefinitionParser::class);
            $this->scope->addModel($model);
            return;
        }

        throw $this->errorUnexpectedToken($token);
    }

    protected function node(): BaseNode
    {
        return $this->scope;
    }
}
