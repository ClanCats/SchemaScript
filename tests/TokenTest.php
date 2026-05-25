<?php

namespace ClanCats\SchemaScript;

use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testLine() : void
    {
        $token = new Token(5, Token::TOKEN_IDENTIFIER, 'User');
        $this->assertEquals(5, $token->getLine());
    }

    public function testType() : void
    {
        $token = new Token(1, Token::TOKEN_IDENTIFIER, 'User');
        $this->assertEquals(Token::TOKEN_IDENTIFIER, $token->getType());
        $this->assertTrue($token->isType(Token::TOKEN_IDENTIFIER));
        $this->assertFalse($token->isType(Token::TOKEN_STRING));
    }

    public function testFilenameDefault() : void
    {
        $token = new Token(1, Token::TOKEN_IDENTIFIER, 'User');
        $this->assertNull($token->getFilename());
    }

    public function testFilename() : void
    {
        $token = new Token(1, Token::TOKEN_IDENTIFIER, 'User', 'test.scsc');
        $this->assertEquals('test.scsc', $token->getFilename());
    }

    public function testGetValueString() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'hello'");
        $this->assertEquals('hello', $token->getValue());

        $token = new Token(1, Token::TOKEN_STRING, '"world"');
        $this->assertEquals('world', $token->getValue());
    }

    public function testGetValueStringEscapedBackslash() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'a\\\\b'");
        $this->assertSame('a\\b', $token->getValue());
    }

    public function testGetValueStringEscapedSingleQuote() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'it\\'s'");
        $this->assertSame("it's", $token->getValue());
    }

    public function testGetValueStringEscapedDoubleQuote() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, '"say \\"hi\\""');
        $this->assertSame('say "hi"', $token->getValue());
    }

    public function testGetValueStringMixedEscapes() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'a\\\\\\'b'");
        $this->assertSame("a\\'b", $token->getValue());
    }

    public function testGetValueStringUnknownEscapePreservesBackslash() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'\\n\\t\\r'");
        $this->assertSame('\\n\\t\\r', $token->getValue());
    }

    public function testGetValueStringOnlyEscapedBackslashes() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'\\\\\\\\'");
        $this->assertSame('\\\\', $token->getValue());
    }

    public function testGetValueStringEmpty() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "''");
        $this->assertSame('', $token->getValue());

        $token = new Token(1, Token::TOKEN_STRING, '""');
        $this->assertSame('', $token->getValue());
    }

    public function testGetValueStringWithNullByte() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'a\x00b'");
        $this->assertSame("a\x00b", $token->getValue());
    }

    public function testGetValueStringTrailingBackslash() : void
    {
        // Raw token value: 'foo\' — the backslash is NOT followed by a recognized escape
        // After stripping quotes we get: foo\
        // The loop sees \ at last position, i+1 is past $len, so it falls through to else branch
        $token = new Token(1, Token::TOKEN_STRING, "'foo\\'");
        // the \' is an escaped quote → value is: foo'  minus the outer quotes
        // Actually wait: raw value is 'foo\' — stripping outer quotes gives foo\
        // But the backslash at the end has no next char... let me think again.
        // Token raw: 'foo\' → substr(1,-1) = foo\ → loop: f,o,o,\ (last char, i+1 >= len) → else: append \
        $this->assertSame('foo\\', $token->getValue());
    }

    public function testGetValueStringCachesResult() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'value'");
        $v1 = $token->getValue();
        $v2 = $token->getValue();
        $this->assertSame('value', $v1);
        $this->assertSame($v1, $v2);
    }

    public function testGetValueStringUnicode() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'héllo'");
        $this->assertSame('héllo', $token->getValue());
    }

    public function testGetValueStringNamespacePath() : void
    {
        $token = new Token(1, Token::TOKEN_STRING, "'App\\\\Models\\\\User'");
        $this->assertSame('App\\Models\\User', $token->getValue());
    }

    public function testGetValueNumber() : void
    {
        $token = new Token(1, Token::TOKEN_NUMBER, '42');
        $this->assertSame(42, $token->getValue());

        $token = new Token(1, Token::TOKEN_NUMBER, '1');
        $this->assertSame(1, $token->getValue());
    }

    public function testGetValueMetadataKey() : void
    {
        $token = new Token(1, Token::TOKEN_METADATA_KEY, '[version]');
        $this->assertEquals('version', $token->getValue());

        $token = new Token(1, Token::TOKEN_METADATA_KEY, '[map:local]');
        $this->assertEquals('map:local', $token->getValue());
    }

    public function testGetValuePassthrough() : void
    {
        $token = new Token(1, Token::TOKEN_IDENTIFIER, 'User');
        $this->assertEquals('User', $token->getValue());

        $token = new Token(1, Token::TOKEN_ANNOTATION, '@local');
        $this->assertEquals('@local', $token->getValue());
    }

    public function testIsValue() : void
    {
        $this->assertTrue((new Token(1, Token::TOKEN_STRING, "'x'"))->isValue());
        $this->assertTrue((new Token(1, Token::TOKEN_NUMBER, '1'))->isValue());
        $this->assertFalse((new Token(1, Token::TOKEN_IDENTIFIER, 'x'))->isValue());
        $this->assertFalse((new Token(1, Token::TOKEN_ANNOTATION, '@x'))->isValue());
        $this->assertFalse((new Token(1, Token::TOKEN_COLON, ':'))->isValue());
    }
}
