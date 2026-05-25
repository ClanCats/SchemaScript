<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Exception\LexerException;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase
{
    protected function tokensFromCode(string $code) : array
    {
        return (new Lexer($code))->tokens();
    }

    protected function assertTokenTypes(string $code, array $expected) : void
    {
        $types = array_map(fn(Token $t) => $t->getType(), $this->tokensFromCode($code));
        $this->assertEquals($expected, $types);
    }

    protected function assertTokenValues(string $code, array $expected) : void
    {
        $values = array_map(fn(Token $t) => $t->getValue(), $this->tokensFromCode($code));
        $this->assertEquals($expected, $values);
    }

    public function testConstruct() : void
    {
        $lexer = new Lexer("  hello   world  ");
        $this->assertEquals("  hello   world  ", $lexer->code());
    }

    public function testTabsProduceSpaceTokens() : void
    {
        $tokens = $this->tokensFromCode("hello\t\tworld");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
        ], $types);
    }

    public function testDuplicateNewlines() : void
    {
        $tokens = $this->tokensFromCode("a\n\n\nb");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_LINE,
            Token::TOKEN_IDENTIFIER,
        ], $types);
    }

    public function testStringTokensSingleQuote() : void
    {
        $this->assertTokenTypes("'int'", [Token::TOKEN_STRING]);

        $tokens = $this->tokensFromCode("'int'");
        $this->assertEquals('int', $tokens[0]->getValue());
    }

    public function testStringTokensDoubleQuote() : void
    {
        $this->assertTokenTypes('"text"', [Token::TOKEN_STRING]);

        $tokens = $this->tokensFromCode('"text"');
        $this->assertEquals('text', $tokens[0]->getValue());
    }

    // -------------------------------------------------------
    // ~ String Parsing
    // -------------------------------------------------------

    public function testStringEmpty() : void
    {
        $tokens = $this->tokensFromCode("''");
        $this->assertSame('', $tokens[0]->getValue());

        $tokens = $this->tokensFromCode('""');
        $this->assertSame('', $tokens[0]->getValue());
    }

    public function testStringEscapedSingleQuoteInSingleQuoted() : void
    {
        $tokens = $this->tokensFromCode("'it\\'s'");
        $this->assertSame("it's", $tokens[0]->getValue());
    }

    public function testStringEscapedDoubleQuoteInDoubleQuoted() : void
    {
        $tokens = $this->tokensFromCode('"say \\"hello\\""');
        $this->assertSame('say "hello"', $tokens[0]->getValue());
    }

    public function testStringEscapedBackslash() : void
    {
        $tokens = $this->tokensFromCode("'path\\\\to'");
        $this->assertSame('path\\to', $tokens[0]->getValue());
    }

    public function testStringDoubleEscapedBackslash() : void
    {
        $tokens = $this->tokensFromCode("'two\\\\\\\\'");
        $this->assertSame('two\\\\', $tokens[0]->getValue());
    }

    public function testStringBackslashBeforeQuote() : void
    {
        // \\' at end: the \\\\ becomes \, then ' closes the string
        $tokens = $this->tokensFromCode("'ends\\\\'");
        $this->assertSame('ends\\', $tokens[0]->getValue());
    }

    public function testStringUnknownEscapeKeptLiteral() : void
    {
        // \n is not a recognized escape, so backslash is preserved
        $tokens = $this->tokensFromCode("'hello\\nworld'");
        $this->assertSame('hello\\nworld', $tokens[0]->getValue());
    }

    public function testStringUnknownEscapeTabKeptLiteral() : void
    {
        $tokens = $this->tokensFromCode("'col\\tcol'");
        $this->assertSame('col\\tcol', $tokens[0]->getValue());
    }

    public function testStringDoubleQuoteInsideSingleQuote() : void
    {
        $tokens = $this->tokensFromCode("'say \"hi\"'");
        $this->assertSame('say "hi"', $tokens[0]->getValue());
    }

    public function testStringSingleQuoteInsideDoubleQuote() : void
    {
        $tokens = $this->tokensFromCode('"it\'s fine"');
        $this->assertSame("it's fine", $tokens[0]->getValue());
    }

    public function testStringPreservesInternalSpaces() : void
    {
        $tokens = $this->tokensFromCode("'hello   world'");
        $this->assertSame('hello   world', $tokens[0]->getValue());
    }

    public function testStringPreservesInternalTabs() : void
    {
        $tokens = $this->tokensFromCode("'col1\tcol2'");
        $this->assertSame("col1\tcol2", $tokens[0]->getValue());
    }

    public function testStringMultiline() : void
    {
        $tokens = $this->tokensFromCode("'line1\nline2'");
        $this->assertSame("line1\nline2", $tokens[0]->getValue());
    }

    public function testStringMultilineTracksLineNumber() : void
    {
        $tokens = $this->tokensFromCode("'line1\nline2'\nfoo");
        $stringToken = $tokens[0];
        $this->assertSame(Token::TOKEN_STRING, $stringToken->getType());
        $this->assertSame(1, $stringToken->getLine());
        // 'foo' should be on line 3 (line1=1, line2=2, foo=3)
        $fooToken = array_values(array_filter($tokens, fn(Token $t) => $t->isType(Token::TOKEN_IDENTIFIER)))[0];
        $this->assertSame(3, $fooToken->getLine());
    }

    public function testStringWithSpecialCharacters() : void
    {
        $tokens = $this->tokensFromCode("'hello@world#123!'");
        $this->assertSame('hello@world#123!', $tokens[0]->getValue());
    }

    public function testStringWithUnicode() : void
    {
        $tokens = $this->tokensFromCode("'héllo wörld'");
        $this->assertSame('héllo wörld', $tokens[0]->getValue());
    }

    public function testStringWithEmoji() : void
    {
        $tokens = $this->tokensFromCode("'test 🎉 emoji'");
        $this->assertSame('test 🎉 emoji', $tokens[0]->getValue());
    }

    public function testStringWithNullByte() : void
    {
        $tokens = $this->tokensFromCode("'before\x00after'");
        $this->assertSame("before\x00after", $tokens[0]->getValue());
    }

    public function testStringWithBraces() : void
    {
        $tokens = $this->tokensFromCode("'some {value} here'");
        $this->assertSame('some {value} here', $tokens[0]->getValue());
    }

    public function testStringWithBrackets() : void
    {
        $tokens = $this->tokensFromCode("'array[0]'");
        $this->assertSame('array[0]', $tokens[0]->getValue());
    }

    public function testStringWithColonsAndEquals() : void
    {
        $tokens = $this->tokensFromCode("'key: value = 1'");
        $this->assertSame('key: value = 1', $tokens[0]->getValue());
    }

    public function testStringWithSlashes() : void
    {
        $tokens = $this->tokensFromCode("'path/to/file'");
        $this->assertSame('path/to/file', $tokens[0]->getValue());
    }

    public function testStringWithBackslashAtEnd() : void
    {
        // single backslash followed by closing quote — the backslash escapes the quote,
        // so the string is not terminated. This should throw.
        $this->expectException(\ClanCats\SchemaScript\Exception\LexerException::class);
        $this->expectExceptionMessage('Unterminated string literal');
        $this->tokensFromCode("'trailing\\");
    }

    public function testStringUnterminatedThrows() : void
    {
        $this->expectException(\ClanCats\SchemaScript\Exception\LexerException::class);
        $this->expectExceptionMessage('Unterminated string literal');
        $this->tokensFromCode("'never closes");
    }

    public function testStringUnterminatedDoubleQuoteThrows() : void
    {
        $this->expectException(\ClanCats\SchemaScript\Exception\LexerException::class);
        $this->expectExceptionMessage('Unterminated string literal');
        $this->tokensFromCode('"never closes');
    }

    public function testStringWithEscapedQuoteAtEnd() : void
    {
        // 'foo\' — backslash escapes the quote, so this is unterminated
        $this->expectException(\ClanCats\SchemaScript\Exception\LexerException::class);
        $this->tokensFromCode("'foo\\'");
    }

    public function testStringConsecutiveBackslashesBeforeQuote() : void
    {
        // 'a\\\\' — four backslashes become two, then ' closes
        $tokens = $this->tokensFromCode("'a\\\\\\\\\\\\\\\\'");
        $this->assertSame('a\\\\\\\\', $tokens[0]->getValue());
    }

    public function testStringOddBackslashesBeforeQuoteIsEscaped() : void
    {
        // 'a\\\\\\' — five chars \\\\\ (3 backslashes), last one escapes quote → unterminated
        $this->expectException(\ClanCats\SchemaScript\Exception\LexerException::class);
        $this->tokensFromCode("'a\\\\\\'");
    }

    public function testStringAdjacentStrings() : void
    {
        $code = "'first' 'second'";
        $tokens = $this->tokensFromCode($code);
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(Token::TOKEN_STRING)));
        $this->assertCount(2, $strings);
        $this->assertSame('first', $strings[0]->getValue());
        $this->assertSame('second', $strings[1]->getValue());
    }

    public function testStringMixedQuoteStyles() : void
    {
        $code = "'single' \"double\"";
        $tokens = $this->tokensFromCode($code);
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(Token::TOKEN_STRING)));
        $this->assertCount(2, $strings);
        $this->assertSame('single', $strings[0]->getValue());
        $this->assertSame('double', $strings[1]->getValue());
    }

    public function testStringInMetadataContext() : void
    {
        $tokens = $this->tokensFromCode("[key] = 'hello\\'s world'");
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(Token::TOKEN_STRING)));
        $this->assertCount(1, $strings);
        $this->assertSame("hello's world", $strings[0]->getValue());
    }

    public function testStringWithNamespacePath() : void
    {
        $tokens = $this->tokensFromCode("'App\\\\Models\\\\User'");
        $this->assertSame('App\\Models\\User', $tokens[0]->getValue());
    }

    public function testStringOnlyBackslashes() : void
    {
        // '\\\\' — two escaped backslashes
        $tokens = $this->tokensFromCode("'\\\\\\\\'");
        $this->assertSame('\\\\', $tokens[0]->getValue());
    }

    public function testStringWhitespaceNotCollapsedInsideString() : void
    {
        $lexer = new Lexer("'multi    spaced\t\ttabbed'");
        $tokens = $lexer->tokens();
        $this->assertSame('multi    spaced' . "\t\t" . 'tabbed', $tokens[0]->getValue());
    }

    public function testStringWhitespacePreserved() : void
    {
        $lexer = new Lexer("[key]   =   'hello   world'");
        $tokens = $lexer->tokens();
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(Token::TOKEN_STRING)));
        $this->assertSame('hello   world', $strings[0]->getValue());
    }

    public function testStringValueCaching() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'cached'");
        $first = $token->getValue();
        $second = $token->getValue();
        $this->assertSame($first, $second);
        $this->assertSame('cached', $first);
    }

    // -------------------------------------------------------
    // ~ Numbers
    // -------------------------------------------------------

    public function testNumberTokens() : void
    {
        $this->assertTokenTypes('42', [Token::TOKEN_NUMBER]);

        $tokens = $this->tokensFromCode('42');
        $this->assertSame(42, $tokens[0]->getValue());
    }

    public function testIdentifiers() : void
    {
        $this->assertTokenTypes('User', [Token::TOKEN_IDENTIFIER]);
        $this->assertTokenTypes('snake_case', [Token::TOKEN_IDENTIFIER]);
        $this->assertTokenTypes('int32', [Token::TOKEN_IDENTIFIER]);
        $this->assertTokenTypes('s1x1', [Token::TOKEN_IDENTIFIER]);
    }

    public function testMetadataKey() : void
    {
        $this->assertTokenTypes('[version]', [Token::TOKEN_METADATA_KEY]);

        $tokens = $this->tokensFromCode('[version]');
        $this->assertEquals('version', $tokens[0]->getValue());

        $tokens = $this->tokensFromCode('[map:local]');
        $this->assertEquals('map:local', $tokens[0]->getValue());
    }

    public function testAnnotation() : void
    {
        $this->assertTokenTypes('@local', [Token::TOKEN_ANNOTATION]);
        $this->assertTokenTypes('@lang.php', [Token::TOKEN_ANNOTATION]);
        $this->assertTokenTypes('@enum', [Token::TOKEN_ANNOTATION]);
    }

    public function testKeywordNs() : void
    {
        $this->assertTokenTypes('ns Foo', [
            Token::TOKEN_KEYWORD_NS,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testKeywordConst() : void
    {
        $this->assertTokenTypes('const bar', [
            Token::TOKEN_KEYWORD_CONST,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testKeywordNsNotGreedy() : void
    {
        $this->assertTokenTypes('namespace', [Token::TOKEN_IDENTIFIER]);
    }

    public function testKeywordConstNotGreedy() : void
    {
        $this->assertTokenTypes('constant', [Token::TOKEN_IDENTIFIER]);
    }

    public function testSymbols() : void
    {
        $this->assertTokenTypes('{', [Token::TOKEN_SCOPE_OPEN]);
        $this->assertTokenTypes('}', [Token::TOKEN_SCOPE_CLOSE]);
        $this->assertTokenTypes('(', [Token::TOKEN_PAREN_OPEN]);
        $this->assertTokenTypes(')', [Token::TOKEN_PAREN_CLOSE]);
        $this->assertTokenTypes(':', [Token::TOKEN_COLON]);
        $this->assertTokenTypes('=', [Token::TOKEN_EQUAL]);
        $this->assertTokenTypes('?', [Token::TOKEN_QUESTION]);
        $this->assertTokenTypes('|', [Token::TOKEN_PIPE]);
        $this->assertTokenTypes(',', [Token::TOKEN_COMMA]);
    }

    public function testDoubleColon() : void
    {
        $this->assertTokenTypes('MappingType::camelCase', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_DOUBLE_COLON,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testArraySuffix() : void
    {
        $this->assertTokenTypes('int[]', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_ARRAY_SUFFIX,
        ]);

        $this->assertTokenTypes('Message[]', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_ARRAY_SUFFIX,
        ]);
    }

    public function testComment() : void
    {
        $tokens = $this->tokensFromCode('// The users ID');
        $this->assertCount(1, $tokens);
        $this->assertEquals(Token::TOKEN_COMMENT, $tokens[0]->getType());
    }

    public function testPropertyDeclaration() : void
    {
        $this->assertTokenTypes('id: int', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_COLON,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testNullableProperty() : void
    {
        $this->assertTokenTypes('avatar_image_id: int?', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_COLON,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_QUESTION,
        ]);
    }

    public function testOptionalKey() : void
    {
        $this->assertTokenTypes('avatar_image?: {', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_QUESTION,
            Token::TOKEN_COLON,
            Token::TOKEN_SPACE,
            Token::TOKEN_SCOPE_OPEN,
        ]);
    }

    public function testMetadataAssignment() : void
    {
        $this->assertTokenTypes('[version] = 1', [
            Token::TOKEN_METADATA_KEY,
            Token::TOKEN_SPACE,
            Token::TOKEN_EQUAL,
            Token::TOKEN_SPACE,
            Token::TOKEN_NUMBER,
        ]);
    }

    public function testNamespaceAccess() : void
    {
        $this->assertTokenTypes('[map:local] = MappingType::camelCase', [
            Token::TOKEN_METADATA_KEY,
            Token::TOKEN_SPACE,
            Token::TOKEN_EQUAL,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_DOUBLE_COLON,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testAnnotationWithArgs() : void
    {
        $this->assertTokenTypes("@lang.php('int')", [
            Token::TOKEN_ANNOTATION,
            Token::TOKEN_PAREN_OPEN,
            Token::TOKEN_STRING,
            Token::TOKEN_PAREN_CLOSE,
        ]);
    }

    public function testEnumAnnotation() : void
    {
        $this->assertTokenTypes('@enum("text", "image")', [
            Token::TOKEN_ANNOTATION,
            Token::TOKEN_PAREN_OPEN,
            Token::TOKEN_STRING,
            Token::TOKEN_COMMA,
            Token::TOKEN_SPACE,
            Token::TOKEN_STRING,
            Token::TOKEN_PAREN_CLOSE,
        ]);
    }

    public function testUnionType() : void
    {
        $this->assertTokenTypes('Image|User', [
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_PIPE,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testNsBlock() : void
    {
        $code = "ns MappingType {\n const camelCase\n const snake_case\n}";
        $this->assertTokenTypes($code, [
            Token::TOKEN_KEYWORD_NS,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_SPACE,
            Token::TOKEN_SCOPE_OPEN,
            Token::TOKEN_LINE,
            Token::TOKEN_SPACE,
            Token::TOKEN_KEYWORD_CONST,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_LINE,
            Token::TOKEN_SPACE,
            Token::TOKEN_KEYWORD_CONST,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_LINE,
            Token::TOKEN_SCOPE_CLOSE,
        ]);
    }

    public function testUnexpectedCharacter() : void
    {
        $this->expectException(LexerException::class);
        $this->tokensFromCode('$');
    }

    public function testFilenameInException() : void
    {
        try {
            (new Lexer('$', 'test.scsc'))->tokens();
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertStringContainsString('test.scsc', $e->getMessage());
        }
    }

    public function testFilenameOnTokens() : void
    {
        $tokens = (new Lexer('User', 'schema.scsc'))->tokens();
        $this->assertEquals('schema.scsc', $tokens[0]->getFilename());
    }

    public function testLineTracking() : void
    {
        $tokens = $this->tokensFromCode("a\nb\nc");
        $identifiers = array_filter($tokens, fn(Token $t) => $t->isType(Token::TOKEN_IDENTIFIER));
        $lines = array_map(fn(Token $t) => $t->getLine(), array_values($identifiers));
        $this->assertEquals([1, 2, 3], $lines);
    }

    public function testFullModelBlock() : void
    {
        $code = "User {\n [version] = 2\n id: int\n avatar_image_id: int?\n}";
        $tokens = $this->tokensFromCode($code);

        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            Token::TOKEN_IDENTIFIER,   // User
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_SCOPE_OPEN,   // {
            Token::TOKEN_LINE,         // \n
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_METADATA_KEY, // [version]
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_EQUAL,        // =
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_NUMBER,       // 2
            Token::TOKEN_LINE,         // \n
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_IDENTIFIER,   // id
            Token::TOKEN_COLON,        // :
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_IDENTIFIER,   // int
            Token::TOKEN_LINE,         // \n
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_IDENTIFIER,   // avatar_image_id
            Token::TOKEN_COLON,        // :
            Token::TOKEN_SPACE,        // ' '
            Token::TOKEN_IDENTIFIER,   // int
            Token::TOKEN_QUESTION,     // ?
            Token::TOKEN_LINE,         // \n
            Token::TOKEN_SCOPE_CLOSE,  // }
        ], $types);
    }

    public function testImportKeyword() : void
    {
        $this->assertTokenTypes('import base', [
            Token::TOKEN_KEYWORD_IMPORT,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testImportAsIdentifierWhenNotKeyword() : void
    {
        $this->assertTokenTypes('importable', [
            Token::TOKEN_IDENTIFIER,
        ]);
    }

    public function testImportWithPath() : void
    {
        $tokens = $this->tokensFromCode("import scsc/base");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            Token::TOKEN_KEYWORD_IMPORT,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_SLASH,
            Token::TOKEN_IDENTIFIER,
        ], $types);
    }

    public function testImportWithDeepPath() : void
    {
        $tokens = $this->tokensFromCode("import models/shared/common");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            Token::TOKEN_KEYWORD_IMPORT,
            Token::TOKEN_SPACE,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_SLASH,
            Token::TOKEN_IDENTIFIER,
            Token::TOKEN_SLASH,
            Token::TOKEN_IDENTIFIER,
        ], $types);
    }

    public function testSlashInCommentStillWorks() : void
    {
        $tokens = $this->tokensFromCode("// this is a comment");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            Token::TOKEN_COMMENT,
        ], $types);
    }
}
