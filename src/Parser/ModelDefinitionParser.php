<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\GenericTypeNode;
use ClanCats\SchemaScript\Node\Type\TypeNode;

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

        $typeParameters = [];
        if (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::AngleOpen)) {
            $this->skipToken();
            while (true) {
                $this->skipTokenOfType([TokenType::Space, TokenType::Line]);
                $paramToken = $this->expectCurrentType(TokenType::Identifier);
                $typeParameters[] = $paramToken->getValue();
                $this->skipToken();
                $this->skipTokenOfType([TokenType::Space, TokenType::Line]);
                if ($this->currentToken()->isType(TokenType::AngleClose)) {
                    $this->skipToken();
                    break;
                }
                $this->expectCurrentType(TokenType::Comma);
                $this->skipToken();
            }
        }

        $parentTypes = [];
        if (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::Colon)) {
            $this->skipToken();
            $this->skipTokenOfType([TokenType::Line]);
            $parentTypes = $this->parseParentTypes();
        }

        $this->expectCurrentType(TokenType::ScopeOpen);
        $bodyTokens = $this->getTokensUntilClosingScope();

        $bodyParser = new ModelBodyParser($bodyTokens);
        /** @var ModelDefinitionNode $model */
        $model = $bodyParser->parse();
        $model->setName($name);
        $model->setTypeParameters($typeParameters);
        $model->setParentTypes($parentTypes);
        $this->capturePosition($model, $nameToken);

        $this->model = $model;
        $this->finish();
    }

    /**
     * @return array<TypeNode>
     */
    private function parseParentTypes(): array
    {
        $parents = [];
        while (true) {
            $this->skipTokenOfType([TokenType::Line]);
            $parentTokens = $this->collectParentTypeTokens();
            $parser = new TypeParser($parentTokens);
            $typeNode = $parser->parse();
            if (!($typeNode instanceof SimpleTypeNode) && !($typeNode instanceof GenericTypeNode)) {
                throw $this->errorParsing('Only model references or generic instantiations can be used as parent types');
            }
            $parents[] = $typeNode;
            $this->skipTokenOfType([TokenType::Line]);
            if ($this->parserIsDone() || !$this->currentToken()->isType(TokenType::Comma)) {
                break;
            }
            $this->skipToken();
        }
        return $parents;
    }

    /**
     * @return array<T>
     */
    private function collectParentTypeTokens(): array
    {
        $tokens = [];
        $angleDepth = 0;
        while (!$this->parserIsDone()) {
            $token = $this->currentToken();
            if ($angleDepth === 0 && ($token->isType(TokenType::Comma) || $token->isType(TokenType::ScopeOpen))) {
                break;
            }
            if ($token->isType(TokenType::AngleOpen)) {
                $angleDepth++;
            } elseif ($token->isType(TokenType::AngleClose)) {
                $angleDepth--;
            }
            $tokens[] = $token;
            $this->skipToken();
        }
        return $tokens;
    }

    protected function node(): BaseNode
    {
        if ($this->model === null) {
            throw $this->errorParsing("Expected a model definition.");
        }

        return $this->model;
    }
}
