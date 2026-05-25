<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;

class ModelDefinitionParser extends SchemaParser
{
    protected ?ModelDefinitionNode $model = null;

    protected function next(): void
    {
        if ($this->currentToken()->isType(T::TOKEN_LINE)) {
            $this->skipToken();
            return;
        }

        $name = $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
        $this->skipToken();

        $this->expectCurrentType(T::TOKEN_SCOPE_OPEN);
        $bodyTokens = $this->getTokensUntilClosingScope();

        $bodyParser = new ModelBodyParser($bodyTokens);
        /** @var ModelDefinitionNode $model */
        $model = $bodyParser->parse();
        $model->setName($name);

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
