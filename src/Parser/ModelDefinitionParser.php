<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;

class ModelDefinitionParser extends SchemaParser
{
    protected ?ModelDefinitionNode $model = null;

    protected function next(): void
    {
        if ($this->currentToken()->isType(TokenType::Line) || $this->currentToken()->isType(TokenType::Comment)) {
            $this->skipToken();
            return;
        }

        $nameToken = $this->expectCurrentType(TokenType::Identifier);
        $name = $nameToken->getValue();
        $this->skipToken();

        $this->expectCurrentType(TokenType::ScopeOpen);
        $bodyTokens = $this->getTokensUntilClosingScope();

        $bodyParser = new ModelBodyParser($bodyTokens);
        /** @var ModelDefinitionNode $model */
        $model = $bodyParser->parse();
        $model->setName($name);
        $this->capturePosition($model, $nameToken);

        $this->model = $model;
        $this->finish();
    }

    protected function node(): BaseNode
    {
        if ($this->model === null) {
            throw $this->errorParsing("Expected a model definition.");
        }

        return $this->model;
    }
}
