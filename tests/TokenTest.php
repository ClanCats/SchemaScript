<?php

namespace ClanCats\SchemaScript;

use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testLine() : void
    {
        $token = new Token(5, TokenType::Identifier, 'User');
        $this->assertEquals(5, $token->getLine());
    }

    public function testType() : void
    {
        $token = new Token(1, TokenType::Identifier, 'User');
        $this->assertEquals(TokenType::Identifier, $token->getType());
        $this->assertTrue($token->isType(TokenType::Identifier));
        $this->assertFalse($token->isType(TokenType::String));
    }

    public function testFilenameDefault() : void
    {
        $token = new Token(1, TokenType::Identifier, 'User');
        $this->assertNull($token->getFilename());
    }

    public function testFilename() : void
    {
        $token = new Token(1, TokenType::Identifier, 'User', 'test.scsc');
        $this->assertEquals('test.scsc', $token->getFilename());
    }

    public function testGetValueString() : void
    {
        $token = new Token(1, TokenType::String, "'hello'");
        $this->assertEquals('hello', $token->getValue());

        $token = new Token(1, TokenType::String, '"world"');
        $this->assertEquals('world', $token->getValue());
    }

    public function testGetValueStringEscapedBackslash() : void
    {
        $token = new Token(1, TokenType::String, "'a\\\\b'");
        $this->assertSame('a\\b', $token->getValue());
    }

    public function testGetValueStringEscapedSingleQuote() : void
    {
        $token = new Token(1, TokenType::String, "'it\\'s'");
        $this->assertSame("it's", $token->getValue());
    }

    public function testGetValueStringEscapedDoubleQuote() : void
    {
        $token = new Token(1, TokenType::String, '"say \\"hi\\""');
        $this->assertSame('say "hi"', $token->getValue());
    }

    public function testGetValueStringMixedEscapes() : void
    {
        $token = new Token(1, TokenType::String, "'a\\\\\\'b'");
        $this->assertSame("a\\'b", $token->getValue());
    }

    public function testGetValueStringUnknownEscapePreservesBackslash() : void
    {
        $token = new Token(1, TokenType::String, "'\\n\\t\\r'");
        $this->assertSame('\\n\\t\\r', $token->getValue());
    }

    public function testGetValueStringOnlyEscapedBackslashes() : void
    {
        $token = new Token(1, TokenType::String, "'\\\\\\\\'");
        $this->assertSame('\\\\', $token->getValue());
    }

    public function testGetValueStringEmpty() : void
    {
        $token = new Token(1, TokenType::String, "''");
        $this->assertSame('', $token->getValue());

        $token = new Token(1, TokenType::String, '""');
        $this->assertSame('', $token->getValue());
    }

    public function testGetValueStringWithNullByte() : void
    {
        $token = new Token(1, TokenType::String, "'a\x00b'");
        $this->assertSame("a\x00b", $token->getValue());
    }

    public function testGetValueStringTrailingBackslash() : void
    {
        // Raw token value: 'foo\' — the backslash is NOT followed by a recognized escape
        // After stripping quotes we get: foo\
        // The loop sees \ at last position, i+1 is past $len, so it falls through to else branch
        $token = new Token(1, TokenType::String, "'foo\\'");
        // the \' is an escaped quote → value is: foo'  minus the outer quotes
        // Actually wait: raw value is 'foo\' — stripping outer quotes gives foo\
        // But the backslash at the end has no next char... let me think again.
        // Token raw: 'foo\' → substr(1,-1) = foo\ → loop: f,o,o,\ (last char, i+1 >= len) → else: append \
        $this->assertSame('foo\\', $token->getValue());
    }

    public function testGetValueStringCachesResult() : void
    {
        $token = new Token(1, TokenType::String, "'value'");
        $v1 = $token->getValue();
        $v2 = $token->getValue();
        $this->assertSame('value', $v1);
        $this->assertSame($v1, $v2);
    }

    public function testGetValueStringUnicode() : void
    {
        $token = new Token(1, TokenType::String, "'héllo'");
        $this->assertSame('héllo', $token->getValue());
    }

    public function testGetValueStringNamespacePath() : void
    {
        $token = new Token(1, TokenType::String, "'App\\\\Models\\\\User'");
        $this->assertSame('App\\Models\\User', $token->getValue());
    }

    public function testGetValueNumber() : void
    {
        $token = new Token(1, TokenType::Number, '42');
        $this->assertSame(42, $token->getValue());

        $token = new Token(1, TokenType::Number, '1');
        $this->assertSame(1, $token->getValue());
    }

    public function testGetValueMetadataKey() : void
    {
        $token = new Token(1, TokenType::MetadataKey, '[version]');
        $this->assertEquals('version', $token->getValue());

        $token = new Token(1, TokenType::MetadataKey, '[map:local]');
        $this->assertEquals('map:local', $token->getValue());
    }

    public function testGetValuePassthrough() : void
    {
        $token = new Token(1, TokenType::Identifier, 'User');
        $this->assertEquals('User', $token->getValue());

        $token = new Token(1, TokenType::Annotation, '@local');
        $this->assertEquals('@local', $token->getValue());
    }

    public function testIsValue() : void
    {
        $this->assertTrue((new Token(1, TokenType::String, "'x'"))->isValue());
        $this->assertTrue((new Token(1, TokenType::Number, '1'))->isValue());
        $this->assertFalse((new Token(1, TokenType::Identifier, 'x'))->isValue());
        $this->assertFalse((new Token(1, TokenType::Annotation, '@x'))->isValue());
        $this->assertFalse((new Token(1, TokenType::Colon, ':'))->isValue());
    }
}
