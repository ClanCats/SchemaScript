<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\ImportNode;

class ImportParser extends SchemaParser
{
    protected ?ImportNode $import = null;

    protected function next(): void
    {
        $this->expectCurrentType(T::TOKEN_KEYWORD_IMPORT);
        $this->skipToken();

        $path = $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
        $this->skipToken();

        while (!$this->parserIsDone() && $this->currentToken()->isType(T::TOKEN_SLASH)) {
            $this->skipToken();
            $path .= '/' . $this->expectCurrentType(T::TOKEN_IDENTIFIER)->getValue();
            $this->skipToken();
        }

        $this->import = new ImportNode($path);
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
