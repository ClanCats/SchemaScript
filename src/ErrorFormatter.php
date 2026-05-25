<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Exception\LexerException;
use ClanCats\SchemaScript\Exception\ParserException;

class ErrorFormatter
{
    public static function format(\Throwable $e, ?string $sourceCode = null): string
    {
        $line = null;
        $column = null;
        $length = null;

        if ($e instanceof LexerException || $e instanceof ParserException) {
            $line = $e->getSourceLine();
            $column = $e->getSourceColumn();
            $length = $e->getSourceLength();
            $sourceCode = $sourceCode ?? $e->getSourceCode();
        }

        if ($line === null || $sourceCode === null) {
            return "Error: " . $e->getMessage();
        }

        $lines = explode("\n", $sourceCode);
        $lineIndex = $line - 1;

        if ($lineIndex < 0 || $lineIndex >= count($lines)) {
            return "Error: " . $e->getMessage();
        }

        $contextStart = max(0, $lineIndex - 1);
        $contextEnd = min(count($lines) - 1, $lineIndex + 1);

        $maxLineNum = $contextEnd + 1;
        $gutterWidth = strlen((string) $maxLineNum);

        $output = "Error: " . $e->getMessage() . "\n\n";

        for ($i = $contextStart; $i <= $contextEnd; $i++) {
            $lineNum = str_pad((string) ($i + 1), $gutterWidth, ' ', STR_PAD_LEFT);
            $output .= "  {$lineNum} | {$lines[$i]}\n";

            if ($i === $lineIndex && $column !== null) {
                $caretLength = max(1, $length ?? 1);
                $padding = str_repeat(' ', $gutterWidth + 3 + ($column - 1));
                $carets = str_repeat('^', $caretLength);
                $output .= "{$padding}{$carets}\n";
            }
        }

        return rtrim($output);
    }
}
