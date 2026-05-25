<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ImportNode;

class ImportParser extends SchemaParser
{
    protected ?ImportNode $import = null;

    protected function next(): void
    {
        $importToken = $this->expectCurrentType(TokenType::KeywordImport);
        $this->skipToken();

        $path = $this->expectCurrentType(TokenType::Identifier)->getValue();
        $this->skipToken();

        while (!$this->parserIsDone() && $this->currentToken()->isType(TokenType::Slash)) {
            $this->skipToken();
            $path .= '/' . $this->expectCurrentType(TokenType::Identifier)->getValue();
            $this->skipToken();
        }

        $this->import = new ImportNode($path);
        $this->capturePosition($this->import, $importToken);
        $this->finish();
    }

    protected function node(): BaseNode
    {
        if ($this->import === null) {
            throw $this->errorParsing("Expected an import statement.");
        }

        return $this->import;
    }
}
