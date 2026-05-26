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
            TokenType::Identifier,
            TokenType::Space,
            TokenType::Identifier,
        ], $types);
    }

    public function testDuplicateNewlines() : void
    {
        $tokens = $this->tokensFromCode("a\n\n\nb");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            TokenType::Identifier,
            TokenType::Line,
            TokenType::Identifier,
        ], $types);
    }

    public function testStringTokensSingleQuote() : void
    {
        $this->assertTokenTypes("'int'", [TokenType::String]);

        $tokens = $this->tokensFromCode("'int'");
        $this->assertEquals('int', $tokens[0]->getValue());
    }

    public function testStringTokensDoubleQuote() : void
    {
        $this->assertTokenTypes('"text"', [TokenType::String]);

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
        $this->assertSame(TokenType::String, $stringToken->getType());
        $this->assertSame(1, $stringToken->getLine());
        // 'foo' should be on line 3 (line1=1, line2=2, foo=3)
        $fooToken = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier)))[0];
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
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::String)));
        $this->assertCount(2, $strings);
        $this->assertSame('first', $strings[0]->getValue());
        $this->assertSame('second', $strings[1]->getValue());
    }

    public function testStringMixedQuoteStyles() : void
    {
        $code = "'single' \"double\"";
        $tokens = $this->tokensFromCode($code);
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::String)));
        $this->assertCount(2, $strings);
        $this->assertSame('single', $strings[0]->getValue());
        $this->assertSame('double', $strings[1]->getValue());
    }

    public function testStringInMetadataContext() : void
    {
        $tokens = $this->tokensFromCode("[key] = 'hello\\'s world'");
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::String)));
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
        $strings = array_values(array_filter($tokens, fn(Token $t) => $t->isType(TokenType::String)));
        $this->assertSame('hello   world', $strings[0]->getValue());
    }

    public function testStringValueCaching() : void
    {
        $token = new Token(1, TokenType::String, "'cached'");
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
        $this->assertTokenTypes('42', [TokenType::Number]);

        $tokens = $this->tokensFromCode('42');
        $this->assertSame(42, $tokens[0]->getValue());
    }

    public function testNegativeIntegerToken() : void
    {
        $this->assertTokenTypes('-42', [TokenType::Number]);

        $tokens = $this->tokensFromCode('-42');
        $this->assertSame(-42, $tokens[0]->getValue());
    }

    public function testNegativeFloatToken() : void
    {
        $this->assertTokenTypes('-3.14', [TokenType::Number]);

        $tokens = $this->tokensFromCode('-3.14');
        $this->assertSame(-3.14, $tokens[0]->getValue());
    }

    public function testNegativeNumberAfterEqual() : void
    {
        $this->assertTokenTypes('= -10', [TokenType::Equal, TokenType::Space, TokenType::Number]);

        $tokens = $this->tokensFromCode('= -10');
        $this->assertSame(-10, $tokens[2]->getValue());
    }

    public function testNegativeNumberAfterComma() : void
    {
        $this->assertTokenTypes(',-5', [TokenType::Comma, TokenType::Number]);

        $tokens = $this->tokensFromCode(',-5');
        $this->assertSame(-5, $tokens[1]->getValue());
    }

    public function testBareMinusStillErrors() : void
    {
        $this->expectException(LexerException::class);
        $this->tokensFromCode('-abc');
    }

    public function testIdentifiers() : void
    {
        $this->assertTokenTypes('User', [TokenType::Identifier]);
        $this->assertTokenTypes('snake_case', [TokenType::Identifier]);
        $this->assertTokenTypes('int32', [TokenType::Identifier]);
        $this->assertTokenTypes('s1x1', [TokenType::Identifier]);
    }

    public function testMetadataKey() : void
    {
        $this->assertTokenTypes('[version]', [TokenType::MetadataKey]);

        $tokens = $this->tokensFromCode('[version]');
        $this->assertEquals('version', $tokens[0]->getValue());

        $tokens = $this->tokensFromCode('[map:local]');
        $this->assertEquals('map:local', $tokens[0]->getValue());
    }

    public function testAnnotation() : void
    {
        $this->assertTokenTypes('@local', [TokenType::Annotation]);
        $this->assertTokenTypes('@lang.php', [TokenType::Annotation]);
        $this->assertTokenTypes('@enum', [TokenType::Annotation]);
    }

    public function testKeywordNs() : void
    {
        $this->assertTokenTypes('ns Foo', [
            TokenType::KeywordNs,
            TokenType::Space,
            TokenType::Identifier,
        ]);
    }

    public function testKeywordConst() : void
    {
        $this->assertTokenTypes('const bar', [
            TokenType::KeywordConst,
            TokenType::Space,
            TokenType::Identifier,
        ]);
    }

    public function testKeywordNsNotGreedy() : void
    {
        $this->assertTokenTypes('namespace', [TokenType::Identifier]);
    }

    public function testKeywordConstNotGreedy() : void
    {
        $this->assertTokenTypes('constant', [TokenType::Identifier]);
    }

    public function testSymbols() : void
    {
        $this->assertTokenTypes('{', [TokenType::ScopeOpen]);
        $this->assertTokenTypes('}', [TokenType::ScopeClose]);
        $this->assertTokenTypes('(', [TokenType::ParenOpen]);
        $this->assertTokenTypes(')', [TokenType::ParenClose]);
        $this->assertTokenTypes(':', [TokenType::Colon]);
        $this->assertTokenTypes('=', [TokenType::Equal]);
        $this->assertTokenTypes('?', [TokenType::Question]);
        $this->assertTokenTypes('|', [TokenType::Pipe]);
        $this->assertTokenTypes(',', [TokenType::Comma]);
    }

    public function testDoubleColon() : void
    {
        $this->assertTokenTypes('MappingType::camelCase', [
            TokenType::Identifier,
            TokenType::DoubleColon,
            TokenType::Identifier,
        ]);
    }

    public function testArraySuffix() : void
    {
        $this->assertTokenTypes('int[]', [
            TokenType::Identifier,
            TokenType::ArraySuffix,
        ]);

        $this->assertTokenTypes('Message[]', [
            TokenType::Identifier,
            TokenType::ArraySuffix,
        ]);
    }

    public function testComment() : void
    {
        $tokens = $this->tokensFromCode('// The users ID');
        $this->assertCount(1, $tokens);
        $this->assertEquals(TokenType::Comment, $tokens[0]->getType());
    }

    public function testPropertyDeclaration() : void
    {
        $this->assertTokenTypes('id: int', [
            TokenType::Identifier,
            TokenType::Colon,
            TokenType::Space,
            TokenType::Identifier,
        ]);
    }

    public function testNullableProperty() : void
    {
        $this->assertTokenTypes('avatar_image_id: int?', [
            TokenType::Identifier,
            TokenType::Colon,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::Question,
        ]);
    }

    public function testOptionalKey() : void
    {
        $this->assertTokenTypes('avatar_image?: {', [
            TokenType::Identifier,
            TokenType::Question,
            TokenType::Colon,
            TokenType::Space,
            TokenType::ScopeOpen,
        ]);
    }

    public function testMetadataAssignment() : void
    {
        $this->assertTokenTypes('[version] = 1', [
            TokenType::MetadataKey,
            TokenType::Space,
            TokenType::Equal,
            TokenType::Space,
            TokenType::Number,
        ]);
    }

    public function testNamespaceAccess() : void
    {
        $this->assertTokenTypes('[map:local] = MappingType::camelCase', [
            TokenType::MetadataKey,
            TokenType::Space,
            TokenType::Equal,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::DoubleColon,
            TokenType::Identifier,
        ]);
    }

    public function testAnnotationWithArgs() : void
    {
        $this->assertTokenTypes("@lang.php('int')", [
            TokenType::Annotation,
            TokenType::ParenOpen,
            TokenType::String,
            TokenType::ParenClose,
        ]);
    }

    public function testEnumAnnotation() : void
    {
        $this->assertTokenTypes('@enum("text", "image")', [
            TokenType::Annotation,
            TokenType::ParenOpen,
            TokenType::String,
            TokenType::Comma,
            TokenType::Space,
            TokenType::String,
            TokenType::ParenClose,
        ]);
    }

    public function testUnionType() : void
    {
        $this->assertTokenTypes('Image|User', [
            TokenType::Identifier,
            TokenType::Pipe,
            TokenType::Identifier,
        ]);
    }

    public function testNsBlock() : void
    {
        $code = "ns MappingType {\n const camelCase\n const snake_case\n}";
        $this->assertTokenTypes($code, [
            TokenType::KeywordNs,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::Space,
            TokenType::ScopeOpen,
            TokenType::Line,
            TokenType::Space,
            TokenType::KeywordConst,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::Line,
            TokenType::Space,
            TokenType::KeywordConst,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::Line,
            TokenType::ScopeClose,
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
        $identifiers = array_filter($tokens, fn(Token $t) => $t->isType(TokenType::Identifier));
        $lines = array_map(fn(Token $t) => $t->getLine(), array_values($identifiers));
        $this->assertEquals([1, 2, 3], $lines);
    }

    public function testFullModelBlock() : void
    {
        $code = "User {\n [version] = 2\n id: int\n avatar_image_id: int?\n}";
        $tokens = $this->tokensFromCode($code);

        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            TokenType::Identifier,   // User
            TokenType::Space,        // ' '
            TokenType::ScopeOpen,   // {
            TokenType::Line,         // \n
            TokenType::Space,        // ' '
            TokenType::MetadataKey, // [version]
            TokenType::Space,        // ' '
            TokenType::Equal,        // =
            TokenType::Space,        // ' '
            TokenType::Number,       // 2
            TokenType::Line,         // \n
            TokenType::Space,        // ' '
            TokenType::Identifier,   // id
            TokenType::Colon,        // :
            TokenType::Space,        // ' '
            TokenType::Identifier,   // int
            TokenType::Line,         // \n
            TokenType::Space,        // ' '
            TokenType::Identifier,   // avatar_image_id
            TokenType::Colon,        // :
            TokenType::Space,        // ' '
            TokenType::Identifier,   // int
            TokenType::Question,     // ?
            TokenType::Line,         // \n
            TokenType::ScopeClose,  // }
        ], $types);
    }

    public function testImportKeyword() : void
    {
        $this->assertTokenTypes('import base', [
            TokenType::KeywordImport,
            TokenType::Space,
            TokenType::Identifier,
        ]);
    }

    public function testImportAsIdentifierWhenNotKeyword() : void
    {
        $this->assertTokenTypes('importable', [
            TokenType::Identifier,
        ]);
    }

    public function testImportWithPath() : void
    {
        $tokens = $this->tokensFromCode("import scsc/base");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            TokenType::KeywordImport,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::Slash,
            TokenType::Identifier,
        ], $types);
    }

    public function testImportWithDeepPath() : void
    {
        $tokens = $this->tokensFromCode("import models/shared/common");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            TokenType::KeywordImport,
            TokenType::Space,
            TokenType::Identifier,
            TokenType::Slash,
            TokenType::Identifier,
            TokenType::Slash,
            TokenType::Identifier,
        ], $types);
    }

    public function testSlashInCommentStillWorks() : void
    {
        $tokens = $this->tokensFromCode("// this is a comment");
        $types = array_map(fn(Token $t) => $t->getType(), $tokens);
        $this->assertEquals([
            TokenType::Comment,
        ], $types);
    }
}
