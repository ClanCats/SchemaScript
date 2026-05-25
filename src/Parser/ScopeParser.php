<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\ImportNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\NamespaceNode;

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

        if ($token->isType(T::TOKEN_LINE)) {
            $this->skipToken();
            return;
        }

        if ($token->isType(T::TOKEN_KEYWORD_IMPORT)) {
            /** @var ImportNode $import */
            $import = $this->parseChild(ImportParser::class);
            $this->scope->addImport($import);
            return;
        }

        if ($token->isType(T::TOKEN_METADATA_KEY)) {
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

        if ($token->isType(T::TOKEN_KEYWORD_NS)) {
            /** @var NamespaceNode $namespace */
            $namespace = $this->parseChild(NamespaceParser::class);
            $this->scope->addNamespace($namespace);
            return;
        }

        if ($token->isType(T::TOKEN_IDENTIFIER)) {
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
