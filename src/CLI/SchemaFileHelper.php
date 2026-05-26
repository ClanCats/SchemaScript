<?php

namespace ClanCats\SchemaScript\CLI;

use ClanCats\SchemaScript\Lexer;
use ClanCats\SchemaScript\ImportLinker;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Schema\SchemaEvaluator;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\SchemaNamespace;
use ClanCats\SchemaScript\ErrorFormatter;
use ClanCats\SchemaScript\Exception\LexerException;
use ClanCats\SchemaScript\Exception\ParserException;
use ClanCats\SchemaScript\Exception\EvaluatorException;
use ClanCats\SchemaScript\Node\ScopeNode;

class SchemaFileHelper
{
    public static function readFile(string $path): ?string
    {
        if (!file_exists($path)) {
            fwrite(STDERR, "Error: File not found: {$path}\n");
            return null;
        }
        return file_get_contents($path) ?: null;
    }

    public static function parse(string $code, string $filename): ?ScopeNode
    {
        try {
            $tokens = (new Lexer($code, $filename))->tokens();
            $node = (new ScopeParser($tokens))->parse();
            assert($node instanceof ScopeNode);
            return $node;
        } catch (LexerException|ParserException $e) {
            if ($e->getSourceCode() === null) {
                $e->setSourceContext(
                    $e->getSourceLine() ?? 0,
                    $e->getSourceColumn() ?? 0,
                    $e->getSourceFile(),
                    $code,
                    $e->getSourceLength()
                );
            }
            fwrite(STDERR, ErrorFormatter::format($e) . "\n");
            return null;
        }
    }

    public static function evaluate(string $code, string $filename): ?Definition
    {
        $scope = self::parse($code, $filename);
        if ($scope === null) {
            return null;
        }

        try {
            $namespace = new SchemaNamespace();
            $namespace->importStdlib();
            $linker = new ImportLinker($namespace);
            $linked = $linker->link($scope, $code, $filename);
            return (new SchemaEvaluator())->evaluate($linked->getScope(), $linked->getSourceCodeMap());
        } catch (EvaluatorException $e) {
            if ($e->getSourceCode() === null && $e->getSourceLine() !== null) {
                $e->setSourceContext(
                    $e->getSourceLine(),
                    $e->getSourceColumn() ?? 0,
                    $e->getSourceFile() ?? $filename,
                    $code,
                    $e->getSourceLength()
                );
            }
            fwrite(STDERR, ErrorFormatter::format($e) . "\n");
            return null;
        }
    }
}
