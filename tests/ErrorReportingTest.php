<?php

namespace ClanCats\SchemaScript;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Exception\LexerException;
use ClanCats\SchemaScript\Exception\ParserException;
use ClanCats\SchemaScript\Parser\ScopeParser;

class ErrorReportingTest extends TestCase
{
    // -------------------------------------------------------
    // ~ Token Column Tracking
    // -------------------------------------------------------

    public function testTokenColumnOnFirstToken(): void
    {
        $tokens = (new Lexer('User'))->tokens();
        $this->assertSame(1, $tokens[0]->getColumn());
    }

    public function testTokenColumnDefault(): void
    {
        $token = new Token(1, TokenType::Identifier, 'test');
        $this->assertSame(0, $token->getColumn());
    }

    public function testTokenColumnExplicit(): void
    {
        $token = new Token(1, TokenType::Identifier, 'test', null, 5);
        $this->assertSame(5, $token->getColumn());
    }

    public function testTokenColumnsOnSingleLine(): void
    {
        $tokens = (new Lexer('id: int'))->tokens();
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));

        // "id" starts at column 1
        $this->assertSame(1, $identifiers[0]->getColumn());
        // ":" at column 3
        $colon = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Colon)));
        $this->assertSame(3, $colon[0]->getColumn());
        // "int" at column 5
        $this->assertSame(5, $identifiers[1]->getColumn());
    }

    public function testTokenColumnResetsAfterNewline(): void
    {
        $tokens = (new Lexer("foo\nbar"))->tokens();
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));

        $this->assertSame(1, $identifiers[0]->getColumn());
        $this->assertSame(1, $identifiers[0]->getLine());
        $this->assertSame(1, $identifiers[1]->getColumn());
        $this->assertSame(2, $identifiers[1]->getLine());
    }

    public function testTokenColumnWithLeadingSpaces(): void
    {
        $tokens = (new Lexer("  name: string"))->tokens();
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));

        // "name" starts at column 3 (after 2 spaces)
        $this->assertSame(3, $identifiers[0]->getColumn());
        // "string" starts at column 9
        $this->assertSame(9, $identifiers[1]->getColumn());
    }

    public function testTokenColumnWithTabs(): void
    {
        $tokens = (new Lexer("\tname"))->tokens();
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));

        // tab is 1 byte, so "name" starts at column 2
        $this->assertSame(2, $identifiers[0]->getColumn());
    }

    public function testTokenColumnOnMultiLineModel(): void
    {
        $code = "User {\n  id: int\n  name: string\n}";
        $tokens = (new Lexer($code))->tokens();
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));

        // "User" at line 1, col 1
        $this->assertSame(1, $identifiers[0]->getLine());
        $this->assertSame(1, $identifiers[0]->getColumn());

        // "id" at line 2, col 3
        $this->assertSame(2, $identifiers[1]->getLine());
        $this->assertSame(3, $identifiers[1]->getColumn());

        // "int" at line 2, col 7
        $this->assertSame(2, $identifiers[2]->getLine());
        $this->assertSame(7, $identifiers[2]->getColumn());

        // "name" at line 3, col 3
        $this->assertSame(3, $identifiers[3]->getLine());
        $this->assertSame(3, $identifiers[3]->getColumn());

        // "string" at line 3, col 9
        $this->assertSame(3, $identifiers[4]->getLine());
        $this->assertSame(9, $identifiers[4]->getColumn());
    }

    public function testTokenColumnForSymbols(): void
    {
        $tokens = (new Lexer('a?:b'))->tokens();
        // a=1, ?=2, :=3, b=4
        $this->assertSame(1, $tokens[0]->getColumn()); // a
        $this->assertSame(2, $tokens[1]->getColumn()); // ?
        $this->assertSame(3, $tokens[2]->getColumn()); // :
        $this->assertSame(4, $tokens[3]->getColumn()); // b
    }

    public function testTokenColumnForDoubleColon(): void
    {
        $tokens = (new Lexer('Ns::val'))->tokens();
        // Ns=1, ::=3, val=5
        $this->assertSame(1, $tokens[0]->getColumn());
        $this->assertSame(3, $tokens[1]->getColumn());
        $this->assertSame(5, $tokens[2]->getColumn());
    }

    public function testTokenColumnForArraySuffix(): void
    {
        $tokens = (new Lexer('int[]'))->tokens();
        // int=1, []=4
        $this->assertSame(1, $tokens[0]->getColumn());
        $this->assertSame(4, $tokens[1]->getColumn());
    }

    public function testTokenColumnForString(): void
    {
        $tokens = (new Lexer("key = 'value'"))->tokens();
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::String)));
        // 'value' starts at column 7
        $this->assertSame(7, $strings[0]->getColumn());
    }

    public function testTokenColumnForMetadataKey(): void
    {
        $tokens = (new Lexer('[version] = 1'))->tokens();
        // [version] starts at column 1
        $this->assertSame(1, $tokens[0]->getColumn());
    }

    public function testTokenColumnForAnnotation(): void
    {
        $tokens = (new Lexer("  @deprecated"))->tokens();
        $annotations = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Annotation)));
        $this->assertSame(3, $annotations[0]->getColumn());
    }

    public function testTokenColumnForComment(): void
    {
        $tokens = (new Lexer('  // comment'))->tokens();
        $comments = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Comment)));
        $this->assertSame(3, $comments[0]->getColumn());
    }

    public function testTokenColumnForMultilineString(): void
    {
        $tokens = (new Lexer("x = 'line1\nline2'"))->tokens();
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::String)));
        // string starts at column 5 on line 1
        $this->assertSame(5, $strings[0]->getColumn());
        $this->assertSame(1, $strings[0]->getLine());
    }

    public function testTokenColumnAfterMultilineString(): void
    {
        $tokens = (new Lexer("'line1\nline2'\nfoo"))->tokens();
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));
        // "foo" is on line 3, column 1
        $this->assertSame(3, $identifiers[0]->getLine());
        $this->assertSame(1, $identifiers[0]->getColumn());
    }

    public function testTokenColumnForNumber(): void
    {
        $tokens = (new Lexer('[version] = 42'))->tokens();
        $numbers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Number)));
        // "42" at column 13
        $this->assertSame(13, $numbers[0]->getColumn());
    }

    public function testTokenColumnForKeywords(): void
    {
        $tokens = (new Lexer("ns Foo {"))->tokens();
        // ns=1, space=3, Foo=4, space=7, {=8
        $this->assertSame(1, $tokens[0]->getColumn()); // ns
        $this->assertSame(4, $tokens[2]->getColumn()); // Foo
        $this->assertSame(8, $tokens[4]->getColumn()); // {
    }

    public function testTokenColumnForConstKeyword(): void
    {
        $tokens = (new Lexer("const bar"))->tokens();
        $this->assertSame(1, $tokens[0]->getColumn()); // const
        // "bar" at col 7
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));
        $this->assertSame(7, $identifiers[0]->getColumn());
    }

    public function testTokenColumnForImportKeyword(): void
    {
        $tokens = (new Lexer("import scsc/base"))->tokens();
        $this->assertSame(1, $tokens[0]->getColumn()); // import
        // "scsc" at col 8
        $identifiers = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)));
        $this->assertSame(8, $identifiers[0]->getColumn());
    }

    // -------------------------------------------------------
    // ~ Lexer Exception Source Context
    // -------------------------------------------------------

    public function testLexerExceptionHasSourceContext(): void
    {
        try {
            (new Lexer('hello $world', 'test.scsc'))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertSame(1, $e->getSourceLine());
            $this->assertSame(7, $e->getSourceColumn());
            $this->assertSame('test.scsc', $e->getSourceFile());
            $this->assertSame('hello $world', $e->getSourceCode());
            $this->assertSame(1, $e->getSourceLength());
        }
    }

    public function testLexerExceptionOnSecondLine(): void
    {
        try {
            (new Lexer("foo\n  \$bar"))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertSame(2, $e->getSourceLine());
            $this->assertSame(3, $e->getSourceColumn());
        }
    }

    public function testLexerExceptionUnterminatedString(): void
    {
        try {
            (new Lexer("key = 'never closes", 'schema.scsc'))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertSame(1, $e->getSourceLine());
            $this->assertSame(7, $e->getSourceColumn());
            $this->assertStringContainsString('Unterminated string literal', $e->getMessage());
            $this->assertSame('schema.scsc', $e->getSourceFile());
        }
    }

    public function testLexerExceptionUnterminatedStringOnLaterLine(): void
    {
        try {
            (new Lexer("foo\nbar = 'never closes"))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertSame(2, $e->getSourceLine());
            $this->assertSame(7, $e->getSourceColumn());
        }
    }

    public function testLexerExceptionPreservesOriginalSource(): void
    {
        $code = "valid\n\$invalid";
        try {
            (new Lexer($code))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertSame($code, $e->getSourceCode());
        }
    }

    // -------------------------------------------------------
    // ~ Parser Exception Source Context
    // -------------------------------------------------------

    public function testParserExceptionUnexpectedTokenHasContext(): void
    {
        $code = "User {\n  id: int\n  )\n}";
        try {
            $tokens = (new Lexer($code, 'model.scsc'))->tokens();
            (new ScopeParser($tokens))->parse();
            $this->fail('Expected ParserException');
        } catch (ParserException $e) {
            $this->assertNotNull($e->getSourceLine());
            $this->assertNotNull($e->getSourceColumn());
            $this->assertSame('model.scsc', $e->getSourceFile());
        }
    }

    public function testParserExceptionUnclosedScope(): void
    {
        $code = "User {\n  id: int";
        try {
            $tokens = (new Lexer($code, 'broken.scsc'))->tokens();
            (new ScopeParser($tokens))->parse();
            $this->fail('Expected ParserException');
        } catch (ParserException $e) {
            $this->assertStringContainsString('Unclosed scope', $e->getMessage());
            $this->assertSame(1, $e->getSourceLine());
            $this->assertSame(6, $e->getSourceColumn());
            $this->assertSame('broken.scsc', $e->getSourceFile());
        }
    }

    public function testParserExceptionMessageIncludesColumn(): void
    {
        $code = "User {\n  )\n}";
        try {
            $tokens = (new Lexer($code))->tokens();
            (new ScopeParser($tokens))->parse();
            $this->fail('Expected ParserException');
        } catch (ParserException $e) {
            $this->assertMatchesRegularExpression('/column \d+/', $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // ~ HasSourceContext Trait
    // -------------------------------------------------------

    public function testSourceContextSetterReturnsFluentInterface(): void
    {
        $e = new LexerException('test');
        $result = $e->setSourceContext(1, 5, 'file.scsc', 'source code', 3);
        $this->assertSame($e, $result);
    }

    public function testSourceContextDefaultsToNull(): void
    {
        $e = new LexerException('test');
        $this->assertNull($e->getSourceLine());
        $this->assertNull($e->getSourceColumn());
        $this->assertNull($e->getSourceLength());
        $this->assertNull($e->getSourceFile());
        $this->assertNull($e->getSourceCode());
    }

    public function testSourceContextAllFieldsSet(): void
    {
        $e = new ParserException('test');
        $e->setSourceContext(10, 5, 'schema.scsc', "line1\nline2", 3);

        $this->assertSame(10, $e->getSourceLine());
        $this->assertSame(5, $e->getSourceColumn());
        $this->assertSame(3, $e->getSourceLength());
        $this->assertSame('schema.scsc', $e->getSourceFile());
        $this->assertSame("line1\nline2", $e->getSourceCode());
    }

    public function testSourceContextNullLength(): void
    {
        $e = new LexerException('test');
        $e->setSourceContext(1, 1, null, 'code');
        $this->assertNull($e->getSourceLength());
        $this->assertNull($e->getSourceFile());
    }

    // -------------------------------------------------------
    // ~ ErrorFormatter
    // -------------------------------------------------------

    public function testFormatWithNonSchemaException(): void
    {
        $e = new \RuntimeException('something broke');
        $result = ErrorFormatter::format($e);
        $this->assertSame('Error: something broke', $result);
    }

    public function testFormatWithExceptionWithoutSourceContext(): void
    {
        $e = new LexerException('no context');
        $result = ErrorFormatter::format($e);
        $this->assertSame('Error: no context', $result);
    }

    public function testFormatWithExceptionWithoutSourceCode(): void
    {
        $e = new ParserException('no source');
        $e->setSourceContext(1, 1, 'file.scsc', null);
        $result = ErrorFormatter::format($e);
        $this->assertSame('Error: no source', $result);
    }

    public function testFormatShowsCaretAtCorrectColumn(): void
    {
        $e = new LexerException('Unexpected character "$"');
        $e->setSourceContext(1, 7, 'test.scsc', 'hello $world', 1);

        $result = ErrorFormatter::format($e);

        $this->assertStringContainsString('Error: Unexpected character "$"', $result);
        $this->assertStringContainsString('1 | hello $world', $result);
        $this->assertStringContainsString('^', $result);
    }

    public function testFormatShowsContextLines(): void
    {
        $source = "line1\nline2\nline3\nline4\nline5";
        $e = new LexerException('error on line 3');
        $e->setSourceContext(3, 1, null, $source, 1);

        $result = ErrorFormatter::format($e);

        // Should show lines 2, 3, 4
        $this->assertStringContainsString('2 | line2', $result);
        $this->assertStringContainsString('3 | line3', $result);
        $this->assertStringContainsString('4 | line4', $result);
        // Should NOT show lines 1 and 5
        $this->assertStringNotContainsString('1 | line1', $result);
        $this->assertStringNotContainsString('5 | line5', $result);
    }

    public function testFormatErrorOnFirstLine(): void
    {
        $source = "bad line\nsecond line\nthird line";
        $e = new LexerException('error');
        $e->setSourceContext(1, 1, null, $source, 3);

        $result = ErrorFormatter::format($e);

        // Should show lines 1 and 2 (no line before)
        $this->assertStringContainsString('1 | bad line', $result);
        $this->assertStringContainsString('2 | second line', $result);
        $this->assertStringNotContainsString('3 |', $result);
    }

    public function testFormatErrorOnLastLine(): void
    {
        $source = "first line\nsecond line\nbad line";
        $e = new LexerException('error');
        $e->setSourceContext(3, 1, null, $source, 3);

        $result = ErrorFormatter::format($e);

        // Should show lines 2 and 3 (no line after)
        $this->assertStringContainsString('2 | second line', $result);
        $this->assertStringContainsString('3 | bad line', $result);
        $this->assertStringNotContainsString('1 |', $result);
    }

    public function testFormatSingleLineSource(): void
    {
        $e = new LexerException('error');
        $e->setSourceContext(1, 1, null, 'only line', 4);

        $result = ErrorFormatter::format($e);

        $this->assertStringContainsString('1 | only line', $result);
        $this->assertStringContainsString('^^^^', $result);
    }

    public function testFormatCaretLengthMatchesTokenLength(): void
    {
        $e = new ParserException('unexpected "User"');
        $e->setSourceContext(1, 1, null, 'User { }', 4);

        $result = ErrorFormatter::format($e);

        $this->assertStringContainsString('^^^^', $result);
        // Should be exactly 4 carets, not 5
        $lines = explode("\n", $result);
        $caretLine = '';
        foreach ($lines as $line) {
            if (str_contains($line, '^')) {
                $caretLine = $line;
                break;
            }
        }
        $this->assertSame(4, substr_count($caretLine, '^'));
    }

    public function testFormatCaretDefaultsToOneWhenLengthIsNull(): void
    {
        $e = new LexerException('error');
        $e->setSourceContext(1, 5, null, 'abcdefgh');

        $result = ErrorFormatter::format($e);

        $lines = explode("\n", $result);
        $caretLine = '';
        foreach ($lines as $line) {
            if (str_contains($line, '^')) {
                $caretLine = $line;
                break;
            }
        }
        $this->assertSame(1, substr_count($caretLine, '^'));
    }

    public function testFormatLineNumberPaddingAlignment(): void
    {
        // Build source with 100+ lines to test multi-digit gutter
        $lines = [];
        for ($i = 1; $i <= 12; $i++) {
            $lines[] = "line $i content";
        }
        $source = implode("\n", $lines);

        $e = new LexerException('error on line 11');
        $e->setSourceContext(11, 1, null, $source, 4);

        $result = ErrorFormatter::format($e);

        // Line numbers should be right-aligned in the gutter
        $this->assertStringContainsString('10 | line 10 content', $result);
        $this->assertStringContainsString('11 | line 11 content', $result);
        $this->assertStringContainsString('12 | line 12 content', $result);
    }

    public function testFormatPassedSourceCodeOverridesExceptionSource(): void
    {
        $e = new LexerException('error');
        $e->setSourceContext(1, 1, null, 'original source', 1);

        $result = ErrorFormatter::format($e, 'override source');

        $this->assertStringContainsString('override source', $result);
        $this->assertStringNotContainsString('original source', $result);
    }

    public function testFormatOutOfRangeLineReturnsSimpleMessage(): void
    {
        $e = new LexerException('error on line 99');
        $e->setSourceContext(99, 1, null, "short\nsource", 1);

        $result = ErrorFormatter::format($e);

        $this->assertSame('Error: error on line 99', $result);
    }

    public function testFormatLineZeroReturnsSimpleMessage(): void
    {
        $e = new LexerException('error');
        $e->setSourceContext(0, 1, null, 'some source', 1);

        $result = ErrorFormatter::format($e);

        $this->assertSame('Error: error', $result);
    }

    // -------------------------------------------------------
    // ~ Lexer getOriginalCode
    // -------------------------------------------------------

    public function testLexerGetOriginalCode(): void
    {
        $source = "  User {\n  id: int\n}";
        $lexer = new Lexer($source, 'test.scsc');
        $this->assertSame($source, $lexer->getOriginalCode());
    }

    // -------------------------------------------------------
    // ~ Integration: End-to-End Error Formatting
    // -------------------------------------------------------

    public function testEndToEndLexerError(): void
    {
        $code = "User {\n  name: string\n  email: \$invalid\n  age: int\n}";

        try {
            (new Lexer($code, 'schema.scsc'))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $result = ErrorFormatter::format($e);

            $this->assertStringContainsString('Error:', $result);
            $this->assertStringContainsString('2 |', $result);
            $this->assertStringContainsString('3 |', $result);
            $this->assertStringContainsString('4 |', $result);
            $this->assertStringContainsString('^', $result);
        }
    }

    public function testEndToEndParserErrorWithSourceInjection(): void
    {
        $code = "User {\n  id: int\n  )\n}";

        try {
            $tokens = (new Lexer($code, 'test.scsc'))->tokens();
            (new ScopeParser($tokens))->parse();
            $this->fail('Expected ParserException');
        } catch (ParserException $e) {
            // Parser exception doesn't carry source by default, inject it
            if ($e->getSourceCode() === null) {
                $e->setSourceContext(
                    $e->getSourceLine() ?? 0,
                    $e->getSourceColumn() ?? 0,
                    $e->getSourceFile(),
                    $code,
                    $e->getSourceLength()
                );
            }

            $result = ErrorFormatter::format($e);

            $this->assertStringContainsString('Error:', $result);
            $this->assertStringContainsString('^', $result);
            $this->assertStringContainsString(')', $result);
        }
    }

    public function testEndToEndUnclosedScopeError(): void
    {
        $code = "User {\n  id: int\n  name: string";

        try {
            $tokens = (new Lexer($code, 'schema.scsc'))->tokens();
            (new ScopeParser($tokens))->parse();
            $this->fail('Expected ParserException');
        } catch (ParserException $e) {
            if ($e->getSourceCode() === null) {
                $e->setSourceContext(
                    $e->getSourceLine() ?? 0,
                    $e->getSourceColumn() ?? 0,
                    $e->getSourceFile(),
                    $code,
                    $e->getSourceLength()
                );
            }

            $result = ErrorFormatter::format($e);

            $this->assertStringContainsString('Error:', $result);
            $this->assertStringContainsString('Unclosed scope', $result);
            // Should point at the opening brace on line 1
            $this->assertStringContainsString('1 | User {', $result);
            $this->assertStringContainsString('^', $result);
        }
    }

    public function testEndToEndErrorOnFirstLineOfFile(): void
    {
        $code = '$invalid';

        try {
            (new Lexer($code, 'broken.scsc'))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $result = ErrorFormatter::format($e);

            $this->assertStringContainsString('1 | $invalid', $result);
            $this->assertStringContainsString('^', $result);
        }
    }

    public function testEndToEndLexerErrorPreservesOriginalWhitespace(): void
    {
        $code = "User {\n\t\tname:   \$string\n}";

        try {
            (new Lexer($code))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $result = ErrorFormatter::format($e);
            // The original line with tabs and multiple spaces should be shown
            $this->assertStringContainsString("\t\tname:   \$string", $result);
        }
    }

    public function testEndToEndMultipleSpacesPreservedInErrorContext(): void
    {
        $code = "User {\n    name:     string\n    email:    \$bad\n    age:      int\n}";

        try {
            (new Lexer($code, 'test.scsc'))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $result = ErrorFormatter::format($e);
            // Context lines should preserve original indentation
            $this->assertStringContainsString('    name:     string', $result);
            $this->assertStringContainsString('    email:    $bad', $result);
            $this->assertStringContainsString('    age:      int', $result);
        }
    }
}
