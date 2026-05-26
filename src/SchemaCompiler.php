<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\SchemaEvaluator;

class SchemaCompiler
{
    public function compile(string $filePath, SchemaNamespace $ns): Definition
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new \RuntimeException(sprintf('Could not read schema file: %s', $filePath));
        }

        $tokens = (new Lexer($code, $filePath))->tokens();
        /** @var ScopeNode $scope */
        $scope = (new ScopeParser($tokens))->parse();

        $linker = new ImportLinker($ns);
        $linked = $linker->link($scope, $code, $filePath);

        return (new SchemaEvaluator())->evaluate($linked->getScope(), $linked->getSourceCodeMap());
    }
}
