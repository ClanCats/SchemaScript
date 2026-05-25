<?php

namespace ClanCats\SchemaScript\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Lexer;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Visitor\ASTPrinter;

class ASTPrinterTest extends TestCase
{
    private function parse(string $code): \ClanCats\SchemaScript\Node\ScopeNode
    {
        $tokens = (new Lexer($code))->tokens();
        $parser = new ScopeParser($tokens);
        return $parser->parse();
    }

    public function testPrintConceptFile(): void
    {
        $code = file_get_contents(__DIR__ . '/../../concept.scsc');
        $scope = $this->parse($code);

        $printer = new ASTPrinter();
        $output = $printer->print($scope);

        $expected = <<<'AST'
Scope
  Import: scsc/base
  Metadata: [version] = 1
  TypeAlias: int64
    Annotation: @lang.php("int")
    Annotation: @lang.ts("bigint")
  TypeAlias: int32
    Annotation: @lang.php("int")
    Annotation: @lang.ts("number")
  Namespace: MappingType
    Constant: camelCase
    Constant: snake_case
  Model: User
    Metadata: [version] = 2
    Metadata: [map:local] = MappingType::camelCase
    Property: id: int
    Property: avatar_image_id: int?
      Annotation: @local(avatarImageId)
    Property: avatar_image?: {...}
      Property: data: Image?
    Property: last_messages: {...}
      Property: cached: bool
      Property: data: Message[]
  Model: Image
    Property: colors: int[]
    Property: proxy: {...}
      Property: s1x1: string
  Model: Message
    Property: unseen: bool
    Property: text: string
    Property: context: {...}
      Property: type: string
        Annotation: @enum("text", "image")
      Property: data: Image|User

AST;

        $this->assertSame($expected, $output);
    }

    public function testPrintSimpleModel(): void
    {
        $scope = $this->parse('User { name: string }');

        $printer = new ASTPrinter();
        $output = $printer->print($scope);

        $expected = <<<'AST'
Scope
  Model: User
    Property: name: string

AST;

        $this->assertSame($expected, $output);
    }

    public function testPrintUnionType(): void
    {
        $scope = $this->parse('Response { data: Image|User|string }');

        $printer = new ASTPrinter();
        $output = $printer->print($scope);

        $this->assertStringContainsString('Property: data: Image|User|string', $output);
    }

    public function testPrintArrayType(): void
    {
        $scope = $this->parse('List { items: int[] }');

        $printer = new ASTPrinter();
        $output = $printer->print($scope);

        $this->assertStringContainsString('Property: items: int[]', $output);
    }

    public function testPrintNullableArrayType(): void
    {
        $scope = $this->parse('List { items: int[]? }');

        $printer = new ASTPrinter();
        $output = $printer->print($scope);

        $this->assertStringContainsString('Property: items: int[]?', $output);
    }

    public function testPrintMetadataWithStringValue(): void
    {
        $scope = $this->parse('[version] = "hello"');

        $printer = new ASTPrinter();
        $output = $printer->print($scope);

        $this->assertStringContainsString('Metadata: [version] = "hello"', $output);
    }

    public function testPrintIsReusable(): void
    {
        $scope = $this->parse('User { name: string }');

        $printer = new ASTPrinter();
        $first = $printer->print($scope);
        $second = $printer->print($scope);

        $this->assertSame($first, $second);
    }
}
