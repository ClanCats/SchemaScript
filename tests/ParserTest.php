<?php

namespace ClanCats\SchemaScript\Tests;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Lexer;
use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Parser\MetadataParser;
use ClanCats\SchemaScript\Parser\AnnotationParser;
use ClanCats\SchemaScript\Parser\NamespaceParser;
use ClanCats\SchemaScript\Parser\ModelDefinitionParser;
use ClanCats\SchemaScript\Parser\PropertyDefinitionParser;
use ClanCats\SchemaScript\Parser\TypeParser;
use ClanCats\SchemaScript\Parser\TypeBlockParser;
use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;
use ClanCats\SchemaScript\Node\MetadataBlockNode;
use ClanCats\SchemaScript\Node\MetadataListNode;
use ClanCats\SchemaScript\Node\ValueNode;
use ClanCats\SchemaScript\Node\ReferenceNode;
use ClanCats\SchemaScript\Node\AnnotationNode;
use ClanCats\SchemaScript\Node\NamespaceNode;
use ClanCats\SchemaScript\Node\ModelDefinitionNode;
use ClanCats\SchemaScript\Node\PropertyNode;
use ClanCats\SchemaScript\Node\Type\SimpleTypeNode;
use ClanCats\SchemaScript\Node\Type\NullableTypeNode;
use ClanCats\SchemaScript\Node\Type\ArrayTypeNode;
use ClanCats\SchemaScript\Node\Type\UnionTypeNode;
use ClanCats\SchemaScript\Node\Type\InlineObjectTypeNode;
use ClanCats\SchemaScript\Node\ImportNode;
use ClanCats\SchemaScript\Exception\ParserException;

class ParserTest extends TestCase
{
    protected function tokenize(string $code): array
    {
        return (new Lexer($code))->tokens();
    }

    protected function prepareTokens(string $code): array
    {
        $tokens = $this->tokenize($code);

        return array_values(array_filter($tokens, function (T $token) {
            return !$token->isType(TokenType::Comment) && !$token->isType(TokenType::Space);
        }));
    }

    protected function parse(string $code): ScopeNode
    {
        $tokens = $this->tokenize($code);
        $parser = new ScopeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ScopeNode::class, $node);
        return $node;
    }

    // --- MetadataParser ---

    public function testMetadataWithNumber(): void
    {
        $scope = $this->parse('[version] = 1');
        $metadata = $scope->getMetadata();
        $this->assertCount(1, $metadata);
        $this->assertEquals('version', $metadata[0]->getKey());
        $this->assertInstanceOf(ValueNode::class, $metadata[0]->getValue());
        $this->assertEquals(1, $metadata[0]->getValue()->getValue());
    }

    public function testMetadataWithString(): void
    {
        $scope = $this->parse('[name] = "hello"');
        $metadata = $scope->getMetadata();
        $this->assertCount(1, $metadata);
        $this->assertEquals('name', $metadata[0]->getKey());
        $this->assertEquals('hello', $metadata[0]->getValue()->getValue());
    }

    public function testMetadataWithReference(): void
    {
        $scope = $this->parse('[map:local] = MappingType::camelCase');
        $metadata = $scope->getMetadata();
        $this->assertCount(1, $metadata);
        $this->assertEquals('map:local', $metadata[0]->getKey());
        $value = $metadata[0]->getValue();
        $this->assertInstanceOf(ReferenceNode::class, $value);
        $this->assertEquals('MappingType', $value->getNamespace());
        $this->assertEquals('camelCase', $value->getConstant());
    }

    public function testMetadataWithBoolean(): void
    {
        $scope = $this->parse('[enabled] = true');
        $metadata = $scope->getMetadata();
        $this->assertCount(1, $metadata);
        $this->assertEquals('enabled', $metadata[0]->getKey());
        $value = $metadata[0]->getValue();
        $this->assertInstanceOf(ValueNode::class, $value);
        $this->assertSame(ValueNode::TYPE_BOOLEAN, $value->getType());
        $this->assertTrue($value->getValue());
    }

    public function testMetadataWithBooleanFalse(): void
    {
        $scope = $this->parse('[disabled] = false');
        $value = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(ValueNode::class, $value);
        $this->assertSame(ValueNode::TYPE_BOOLEAN, $value->getType());
        $this->assertFalse($value->getValue());
    }

    public function testMetadataWithObjectBlock(): void
    {
        $scope = $this->parse("[config] = {\n  foo = 'bar'\n  num = 42\n}");
        $metadata = $scope->getMetadata();
        $this->assertCount(1, $metadata);
        $this->assertEquals('config', $metadata[0]->getKey());
        $value = $metadata[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $value);
        $entries = $value->getEntries();
        $this->assertCount(2, $entries);
        $this->assertEquals('foo', $entries[0]->getKey());
        $this->assertEquals('bar', $entries[0]->getValue()->getValue());
        $this->assertEquals('num', $entries[1]->getKey());
        $this->assertEquals(42, $entries[1]->getValue()->getValue());
    }

    public function testMetadataWithNestedObjectBlock(): void
    {
        $scope = $this->parse("[outer] = {\n  inner = {\n    deep = 'value'\n  }\n}");
        $metadata = $scope->getMetadata();
        $block = $metadata[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $innerEntry = $block->getEntries()[0];
        $this->assertEquals('inner', $innerEntry->getKey());
        $innerBlock = $innerEntry->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $innerBlock);
        $this->assertEquals('deep', $innerBlock->getEntries()[0]->getKey());
        $this->assertEquals('value', $innerBlock->getEntries()[0]->getValue()->getValue());
    }

    public function testMetadataWithList(): void
    {
        $scope = $this->parse("[colors] = {'red', 'green', 'blue'}");
        $value = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $value);
        $items = $value->getItems();
        $this->assertCount(3, $items);
        $this->assertEquals('red', $items[0]->getValue());
        $this->assertEquals('green', $items[1]->getValue());
        $this->assertEquals('blue', $items[2]->getValue());
    }

    public function testMetadataWithEmptyBlock(): void
    {
        $scope = $this->parse("[empty] = {}");
        $value = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $value);
        $this->assertCount(0, $value->getEntries());
    }

    public function testMetadataBlockWithMetadataKeys(): void
    {
        $scope = $this->parse("[block] = {\n  [keyA] = 'val1'\n  [keyB] = 'val2'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);
        $this->assertEquals('keyA', $entries[0]->getKey());
        $this->assertEquals('val1', $entries[0]->getValue()->getValue());
        $this->assertEquals('keyB', $entries[1]->getKey());
        $this->assertEquals('val2', $entries[1]->getValue()->getValue());
    }

    public function testMetadataBlockWithAnnotatedEntries(): void
    {
        $scope = $this->parse("[meta] = {\n  @a('example')\n  value = 'fooo'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(1, $entries);
        $this->assertEquals('value', $entries[0]->getKey());
        $annotations = $entries[0]->getAnnotations();
        $this->assertCount(1, $annotations);
        $this->assertEquals('a', $annotations[0]->getName());
    }

    public function testMetadataBlockWithStandaloneIdentifier(): void
    {
        $scope = $this->parse("[meta] = {\n  @a('example')\n  someIdentifier\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(1, $entries);
        $this->assertEquals('someIdentifier', $entries[0]->getKey());
        $this->assertNull($entries[0]->getValue());
        $this->assertCount(1, $entries[0]->getAnnotations());
    }

    public function testMetadataBlockWithBooleanValue(): void
    {
        $scope = $this->parse("[config] = {\n  enabled = true\n  disabled = false\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries[0]->getValue()->getValue());
        $this->assertFalse($entries[1]->getValue()->getValue());
    }

    public function testMetadataBlockWithListValue(): void
    {
        $scope = $this->parse("[config] = {\n  items = {'a', 'b'}\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $listValue = $block->getEntries()[0]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $listValue);
        $this->assertCount(2, $listValue->getItems());
    }

    public function testMetadataWithDottedKey(): void
    {
        $scope = $this->parse("[php.mappers] = 'test'");
        $this->assertEquals('php.mappers', $scope->getMetadata()[0]->getKey());
    }

    public function testMetadataListWithNumbers(): void
    {
        $scope = $this->parse("[nums] = {1, 2, 3}");
        $value = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $value);
        $items = $value->getItems();
        $this->assertCount(3, $items);
        $this->assertSame(1, $items[0]->getValue());
        $this->assertSame(2, $items[1]->getValue());
        $this->assertSame(3, $items[2]->getValue());
    }

    public function testMetadataListWithSingleElement(): void
    {
        $scope = $this->parse("[single] = {'only'}");
        $value = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $value);
        $this->assertCount(1, $value->getItems());
        $this->assertSame('only', $value->getItems()[0]->getValue());
    }

    public function testMetadataListMixedTypes(): void
    {
        $scope = $this->parse("[mixed] = {'text', 42, 3.14}");
        $value = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $value);
        $items = $value->getItems();
        $this->assertCount(3, $items);
        $this->assertSame('text', $items[0]->getValue());
        $this->assertSame(42, $items[1]->getValue());
        $this->assertSame(3.14, $items[2]->getValue());
    }

    public function testMetadataBlockWithFloat(): void
    {
        $scope = $this->parse("[data] = {\n  rate = 3.14\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $this->assertSame(3.14, $block->getEntries()[0]->getValue()->getValue());
    }

    public function testMetadataBlockDuplicateMetadataKeys(): void
    {
        $scope = $this->parse("[gen] = {\n  [php.mappers] = 'v1'\n  [php.mappers] = 'v2'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);
        $this->assertEquals('php.mappers', $entries[0]->getKey());
        $this->assertEquals('php.mappers', $entries[1]->getKey());
        $this->assertEquals('v1', $entries[0]->getValue()->getValue());
        $this->assertEquals('v2', $entries[1]->getValue()->getValue());
    }

    public function testMetadataBlockMixedEntryStyles(): void
    {
        $scope = $this->parse("[mix] = {\n  [bracketKey] = 'val1'\n  plainKey = 'val2'\n  @tag\n  standaloneId\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(3, $entries);
        $this->assertEquals('bracketKey', $entries[0]->getKey());
        $this->assertEquals('val1', $entries[0]->getValue()->getValue());
        $this->assertEquals('plainKey', $entries[1]->getKey());
        $this->assertEquals('val2', $entries[1]->getValue()->getValue());
        $this->assertEquals('standaloneId', $entries[2]->getKey());
        $this->assertNull($entries[2]->getValue());
        $this->assertCount(1, $entries[2]->getAnnotations());
        $this->assertEquals('tag', $entries[2]->getAnnotations()[0]->getName());
    }

    public function testMetadataBlockMultipleAnnotationsOnEntry(): void
    {
        $scope = $this->parse("[meta] = {\n  @first\n  @second('arg')\n  entry = 'val'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(1, $entries);
        $annotations = $entries[0]->getAnnotations();
        $this->assertCount(2, $annotations);
        $this->assertEquals('first', $annotations[0]->getName());
        $this->assertEmpty($annotations[0]->getArguments());
        $this->assertEquals('second', $annotations[1]->getName());
        $this->assertCount(1, $annotations[1]->getArguments());
        $this->assertEquals('arg', $annotations[1]->getArguments()[0]->getValue());
    }

    public function testMetadataBlockAnnotationOnMetadataKeyEntry(): void
    {
        $scope = $this->parse("[meta] = {\n  @tag('x')\n  [key] = 'val'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(1, $entries);
        $this->assertEquals('key', $entries[0]->getKey());
        $this->assertCount(1, $entries[0]->getAnnotations());
        $this->assertEquals('tag', $entries[0]->getAnnotations()[0]->getName());
    }

    public function testMetadataThreeLevelNesting(): void
    {
        $code = "[root] = {\n  level1 = {\n    level2 = {\n      level3 = 'deepest'\n    }\n  }\n}";
        $scope = $this->parse($code);
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $l1 = $block->getEntries()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $l1);
        $l2 = $l1->getEntries()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $l2);
        $l3 = $l2->getEntries()[0];
        $this->assertEquals('level3', $l3->getKey());
        $this->assertEquals('deepest', $l3->getValue()->getValue());
    }

    public function testMetadataBlockWithNestedList(): void
    {
        $scope = $this->parse("[config] = {\n  tags = {'a', 'b'}\n  nested = {\n    items = {1, 2, 3}\n  }\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(MetadataListNode::class, $entries[0]->getValue());
        $this->assertCount(2, $entries[0]->getValue()->getItems());
        $nestedBlock = $entries[1]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $nestedBlock);
        $innerList = $nestedBlock->getEntries()[0]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $innerList);
        $this->assertCount(3, $innerList->getItems());
    }

    public function testMetadataBlockWithIdentifierValue(): void
    {
        $scope = $this->parse("[config] = {\n  mode = someIdentifier\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entry = $block->getEntries()[0];
        $this->assertEquals('mode', $entry->getKey());
        $value = $entry->getValue();
        $this->assertInstanceOf(ValueNode::class, $value);
        $this->assertSame(ValueNode::TYPE_IDENTIFIER, $value->getType());
        $this->assertEquals('someIdentifier', $value->getValue());
    }

    public function testMetadataBlockWithReferenceValue(): void
    {
        $scope = $this->parse("[config] = {\n  mapping = MappingType::camelCase\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entry = $block->getEntries()[0];
        $this->assertEquals('mapping', $entry->getKey());
        $value = $entry->getValue();
        $this->assertInstanceOf(ReferenceNode::class, $value);
        $this->assertEquals('MappingType', $value->getNamespace());
        $this->assertEquals('camelCase', $value->getConstant());
    }

    public function testMetadataMultipleStandaloneIdentifiers(): void
    {
        $scope = $this->parse("[flags] = {\n  @a('x')\n  flagOne\n  @b('y')\n  flagTwo\n  flagThree\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(3, $entries);
        $this->assertEquals('flagOne', $entries[0]->getKey());
        $this->assertNull($entries[0]->getValue());
        $this->assertCount(1, $entries[0]->getAnnotations());
        $this->assertEquals('flagTwo', $entries[1]->getKey());
        $this->assertNull($entries[1]->getValue());
        $this->assertCount(1, $entries[1]->getAnnotations());
        $this->assertEquals('flagThree', $entries[2]->getKey());
        $this->assertNull($entries[2]->getValue());
        $this->assertCount(0, $entries[2]->getAnnotations());
    }

    public function testMetadataBlockWithNestedBlockContainingMetadataKeys(): void
    {
        $code = "[generate] = {\n  [php.mappers] = {\n    version = 1\n    output = 'src/V1/'\n  }\n  [php.mappers] = {\n    version = 2\n    output = 'src/V2/'\n  }\n}";
        $scope = $this->parse($code);
        $block = $scope->getMetadata()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $block);
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);

        $this->assertEquals('php.mappers', $entries[0]->getKey());
        $v1Block = $entries[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $v1Block);
        $v1Entries = $v1Block->getEntries();
        $this->assertCount(2, $v1Entries);
        $this->assertEquals('version', $v1Entries[0]->getKey());
        $this->assertSame(1, $v1Entries[0]->getValue()->getValue());
        $this->assertEquals('output', $v1Entries[1]->getKey());
        $this->assertSame('src/V1/', $v1Entries[1]->getValue()->getValue());

        $this->assertEquals('php.mappers', $entries[1]->getKey());
        $v2Block = $entries[1]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $v2Block);
        $v2Entries = $v2Block->getEntries();
        $this->assertEquals('version', $v2Entries[0]->getKey());
        $this->assertSame(2, $v2Entries[0]->getValue()->getValue());
    }

    public function testMetadataBlockWithSingleQuoteStrings(): void
    {
        $scope = $this->parse("[config] = {\n  name = 'hello world'\n}");
        $entry = $scope->getMetadata()[0]->getValue()->getEntries()[0];
        $this->assertEquals('hello world', $entry->getValue()->getValue());
    }

    public function testMetadataBlockWithDoubleQuoteStrings(): void
    {
        $scope = $this->parse('[config] = {' . "\n" . '  name = "hello world"' . "\n" . '}');
        $entry = $scope->getMetadata()[0]->getValue()->getEntries()[0];
        $this->assertEquals('hello world', $entry->getValue()->getValue());
    }

    public function testMultipleGlobalMetadataEntries(): void
    {
        $scope = $this->parse("[version] = 1\n[name] = 'test'\n[enabled] = true");
        $metadata = $scope->getMetadata();
        $this->assertCount(3, $metadata);
        $this->assertEquals('version', $metadata[0]->getKey());
        $this->assertEquals('name', $metadata[1]->getKey());
        $this->assertEquals('enabled', $metadata[2]->getKey());
    }

    public function testModelWithMultipleMetadataEntries(): void
    {
        $code = "User {\n  [version] = 2\n  [map] = 'camel'\n  [deprecated] = false\n  id: int\n}";
        $scope = $this->parse($code);
        $model = $scope->getModels()[0];
        $metadata = $model->getMetadata();
        $this->assertCount(3, $metadata);
        $this->assertEquals('version', $metadata[0]->getKey());
        $this->assertSame(2, $metadata[0]->getValue()->getValue());
        $this->assertEquals('map', $metadata[1]->getKey());
        $this->assertEquals('camel', $metadata[1]->getValue()->getValue());
        $this->assertEquals('deprecated', $metadata[2]->getKey());
        $this->assertFalse($metadata[2]->getValue()->getValue());
    }

    public function testMetadataBlockEntryWithNestedBlockAndList(): void
    {
        $code = "[complex] = {\n  settings = {\n    colors = {'red', 'blue'}\n    size = 42\n  }\n  tags = {'a', 'b', 'c'}\n}";
        $scope = $this->parse($code);
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);

        $settingsBlock = $entries[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $settingsBlock);
        $settingsEntries = $settingsBlock->getEntries();
        $this->assertCount(2, $settingsEntries);
        $this->assertInstanceOf(MetadataListNode::class, $settingsEntries[0]->getValue());
        $this->assertCount(2, $settingsEntries[0]->getValue()->getItems());
        $this->assertSame(42, $settingsEntries[1]->getValue()->getValue());

        $tagsList = $entries[1]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $tagsList);
        $this->assertCount(3, $tagsList->getItems());
    }

    public function testMetadataBlockAllValueTypes(): void
    {
        $code = "[all] = {\n  str = 'example'\n  num = 42\n  flt = 3.14\n  yes = true\n  no = false\n  arr = {'a', 'b'}\n  obj = {\n    key = 'val'\n  }\n}";
        $scope = $this->parse($code);
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(7, $entries);

        $this->assertEquals('str', $entries[0]->getKey());
        $this->assertSame('example', $entries[0]->getValue()->getValue());

        $this->assertEquals('num', $entries[1]->getKey());
        $this->assertSame(42, $entries[1]->getValue()->getValue());

        $this->assertEquals('flt', $entries[2]->getKey());
        $this->assertSame(3.14, $entries[2]->getValue()->getValue());

        $this->assertEquals('yes', $entries[3]->getKey());
        $this->assertTrue($entries[3]->getValue()->getValue());

        $this->assertEquals('no', $entries[4]->getKey());
        $this->assertFalse($entries[4]->getValue()->getValue());

        $this->assertEquals('arr', $entries[5]->getKey());
        $this->assertInstanceOf(MetadataListNode::class, $entries[5]->getValue());
        $this->assertCount(2, $entries[5]->getValue()->getItems());

        $this->assertEquals('obj', $entries[6]->getKey());
        $this->assertInstanceOf(MetadataBlockNode::class, $entries[6]->getValue());
        $this->assertCount(1, $entries[6]->getValue()->getEntries());
    }

    public function testMetadataEntryNodeAnnotationsPreserved(): void
    {
        $scope = $this->parse("[meta] = {\n  @first\n  @second('a', 'b')\n  @third(42)\n  entry = 'val'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entry = $block->getEntries()[0];
        $annotations = $entry->getAnnotations();
        $this->assertCount(3, $annotations);
        $this->assertEquals('first', $annotations[0]->getName());
        $this->assertEmpty($annotations[0]->getArguments());
        $this->assertEquals('second', $annotations[1]->getName());
        $this->assertCount(2, $annotations[1]->getArguments());
        $this->assertSame('a', $annotations[1]->getArguments()[0]->getValue());
        $this->assertSame('b', $annotations[1]->getArguments()[1]->getValue());
        $this->assertEquals('third', $annotations[2]->getName());
        $this->assertCount(1, $annotations[2]->getArguments());
        $this->assertSame(42, $annotations[2]->getArguments()[0]->getValue());
    }

    public function testMetadataBlockAnnotationsOnlyAttachToNextEntry(): void
    {
        $scope = $this->parse("[meta] = {\n  @tag1\n  first = 'a'\n  second = 'b'\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);
        $this->assertCount(1, $entries[0]->getAnnotations());
        $this->assertCount(0, $entries[1]->getAnnotations());
    }

    public function testMetadataBlockAnnotationsOnStandaloneIdentifiers(): void
    {
        $scope = $this->parse("[flags] = {\n  @deprecated\n  @since('v2')\n  oldFlag\n  newFlag\n}");
        $block = $scope->getMetadata()[0]->getValue();
        $entries = $block->getEntries();
        $this->assertCount(2, $entries);
        $this->assertEquals('oldFlag', $entries[0]->getKey());
        $this->assertNull($entries[0]->getValue());
        $this->assertCount(2, $entries[0]->getAnnotations());
        $this->assertEquals('deprecated', $entries[0]->getAnnotations()[0]->getName());
        $this->assertEquals('since', $entries[0]->getAnnotations()[1]->getName());
        $this->assertEquals('newFlag', $entries[1]->getKey());
        $this->assertNull($entries[1]->getValue());
        $this->assertCount(0, $entries[1]->getAnnotations());
    }

    public function testMetadataAndModelsCoexist(): void
    {
        $code = "[version] = 1\n[config] = {\n  debug = true\n}\nUser {\n  [table] = 'users'\n  id: int\n  name: string\n}\nPost {\n  [table] = 'posts'\n  title: string\n}";
        $scope = $this->parse($code);
        $this->assertCount(2, $scope->getMetadata());
        $this->assertCount(2, $scope->getModels());

        $user = $scope->getModels()[0];
        $this->assertEquals('User', $user->getName());
        $this->assertCount(1, $user->getMetadata());
        $this->assertEquals('table', $user->getMetadata()[0]->getKey());

        $post = $scope->getModels()[1];
        $this->assertEquals('Post', $post->getName());
        $this->assertCount(1, $post->getMetadata());
        $this->assertEquals('table', $post->getMetadata()[0]->getKey());
    }

    public function testMetadataExamplesFile(): void
    {
        $code = <<<'SCSC'
[version] = 1

[somthing] = {
  foo = 'bar'
  nested = {
    deeper = {
      value = 42
    }
  }
}

[colors] = {'red', 'green', 'blue'}

User {
  [spcial] = 'value'
  [alias] = {
    frontend = 'Account'
    backend = 'User'
  }

  id: int
}

[block] = {
    [keyA] = 'example1'
    [keyB] = 'example2'
}

[metadata] = {
  string = 'example'
  int = 42
  float = 3.14
  bool = true
  array = {'a', 'b', 'c'}
  object = {
    key1 = 'value1'
    key2 = 'value2'
  }
}

[metadataWithAttributes] = {
  @a('example')
  value = 'fooo'
}

[metadataWithIdentifier] = {
  @a('example')
  value = someIdentifier
}

[metadataWithIdentifierOnly] = {
  @a('example')
  someIdentifier

  @a('another')
  anotherIdentifier
}
SCSC;
        $scope = $this->parse($code);

        $metadata = $scope->getMetadata();

        // [version] = 1
        $this->assertEquals('version', $metadata[0]->getKey());
        $this->assertSame(1, $metadata[0]->getValue()->getValue());

        // [somthing] = { foo = 'bar', nested = { deeper = { value = 42 } } }
        $this->assertEquals('somthing', $metadata[1]->getKey());
        $somthing = $metadata[1]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $somthing);
        $somthingEntries = $somthing->getEntries();
        $this->assertCount(2, $somthingEntries);
        $this->assertEquals('foo', $somthingEntries[0]->getKey());
        $this->assertEquals('bar', $somthingEntries[0]->getValue()->getValue());
        $nestedBlock = $somthingEntries[1]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $nestedBlock);
        $deeperBlock = $nestedBlock->getEntries()[0]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $deeperBlock);
        $this->assertSame(42, $deeperBlock->getEntries()[0]->getValue()->getValue());

        // [colors] = {'red', 'green', 'blue'}
        $this->assertEquals('colors', $metadata[2]->getKey());
        $colors = $metadata[2]->getValue();
        $this->assertInstanceOf(MetadataListNode::class, $colors);
        $this->assertCount(3, $colors->getItems());

        // [block] = { [keyA] = 'example1', [keyB] = 'example2' }
        $this->assertEquals('block', $metadata[3]->getKey());
        $blockEntries = $metadata[3]->getValue()->getEntries();
        $this->assertCount(2, $blockEntries);
        $this->assertEquals('keyA', $blockEntries[0]->getKey());
        $this->assertEquals('keyB', $blockEntries[1]->getKey());

        // [metadata] = { string, int, float, bool, array, object }
        $this->assertEquals('metadata', $metadata[4]->getKey());
        $allTypes = $metadata[4]->getValue()->getEntries();
        $this->assertCount(6, $allTypes);
        $this->assertEquals('string', $allTypes[0]->getKey());
        $this->assertSame('example', $allTypes[0]->getValue()->getValue());
        $this->assertEquals('int', $allTypes[1]->getKey());
        $this->assertSame(42, $allTypes[1]->getValue()->getValue());
        $this->assertEquals('float', $allTypes[2]->getKey());
        $this->assertSame(3.14, $allTypes[2]->getValue()->getValue());
        $this->assertEquals('bool', $allTypes[3]->getKey());
        $this->assertTrue($allTypes[3]->getValue()->getValue());
        $this->assertEquals('array', $allTypes[4]->getKey());
        $this->assertInstanceOf(MetadataListNode::class, $allTypes[4]->getValue());
        $this->assertEquals('object', $allTypes[5]->getKey());
        $this->assertInstanceOf(MetadataBlockNode::class, $allTypes[5]->getValue());

        // [metadataWithAttributes] = { @a('example') value = 'fooo' }
        $this->assertEquals('metadataWithAttributes', $metadata[5]->getKey());
        $attrEntries = $metadata[5]->getValue()->getEntries();
        $this->assertCount(1, $attrEntries);
        $this->assertEquals('value', $attrEntries[0]->getKey());
        $this->assertSame('fooo', $attrEntries[0]->getValue()->getValue());
        $this->assertCount(1, $attrEntries[0]->getAnnotations());
        $this->assertEquals('a', $attrEntries[0]->getAnnotations()[0]->getName());

        // [metadataWithIdentifier] = { @a('example') value = someIdentifier }
        $this->assertEquals('metadataWithIdentifier', $metadata[6]->getKey());
        $identEntry = $metadata[6]->getValue()->getEntries()[0];
        $this->assertEquals('value', $identEntry->getKey());
        $this->assertSame(ValueNode::TYPE_IDENTIFIER, $identEntry->getValue()->getType());
        $this->assertEquals('someIdentifier', $identEntry->getValue()->getValue());

        // [metadataWithIdentifierOnly] = { @a('example') someIdentifier, @a('another') anotherIdentifier }
        $this->assertEquals('metadataWithIdentifierOnly', $metadata[7]->getKey());
        $identOnlyEntries = $metadata[7]->getValue()->getEntries();
        $this->assertCount(2, $identOnlyEntries);
        $this->assertEquals('someIdentifier', $identOnlyEntries[0]->getKey());
        $this->assertNull($identOnlyEntries[0]->getValue());
        $this->assertCount(1, $identOnlyEntries[0]->getAnnotations());
        $this->assertEquals('a', $identOnlyEntries[0]->getAnnotations()[0]->getName());
        $this->assertEquals('example', $identOnlyEntries[0]->getAnnotations()[0]->getArguments()[0]->getValue());
        $this->assertEquals('anotherIdentifier', $identOnlyEntries[1]->getKey());
        $this->assertNull($identOnlyEntries[1]->getValue());
        $this->assertCount(1, $identOnlyEntries[1]->getAnnotations());
        $this->assertEquals('another', $identOnlyEntries[1]->getAnnotations()[0]->getArguments()[0]->getValue());

        // Model: User
        $models = $scope->getModels();
        $this->assertCount(1, $models);
        $user = $models[0];
        $this->assertEquals('User', $user->getName());
        $userMeta = $user->getMetadata();
        $this->assertCount(2, $userMeta);
        $this->assertEquals('spcial', $userMeta[0]->getKey());
        $this->assertEquals('value', $userMeta[0]->getValue()->getValue());
        $this->assertEquals('alias', $userMeta[1]->getKey());
        $aliasBlock = $userMeta[1]->getValue();
        $this->assertInstanceOf(MetadataBlockNode::class, $aliasBlock);
        $aliasEntries = $aliasBlock->getEntries();
        $this->assertCount(2, $aliasEntries);
        $this->assertEquals('frontend', $aliasEntries[0]->getKey());
        $this->assertEquals('Account', $aliasEntries[0]->getValue()->getValue());
        $this->assertEquals('backend', $aliasEntries[1]->getKey());
        $this->assertEquals('User', $aliasEntries[1]->getValue()->getValue());
    }

    // --- AnnotationParser ---

    public function testAnnotationWithoutArguments(): void
    {
        $tokens = $this->prepareTokens('@deprecated');
        $parser = new AnnotationParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(AnnotationNode::class, $node);
        $this->assertEquals('deprecated', $node->getName());
        $this->assertEmpty($node->getArguments());
    }

    public function testAnnotationWithIdentifierArgument(): void
    {
        $tokens = $this->prepareTokens('@local(avatarImageId)');
        $parser = new AnnotationParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(AnnotationNode::class, $node);
        $this->assertEquals('local', $node->getName());
        $this->assertCount(1, $node->getArguments());
        $this->assertEquals('avatarImageId', $node->getArguments()[0]->getValue());
    }

    public function testAnnotationWithStringArguments(): void
    {
        $tokens = $this->prepareTokens('@enum("text", "image")');
        $parser = new AnnotationParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(AnnotationNode::class, $node);
        $this->assertEquals('enum', $node->getName());
        $this->assertCount(2, $node->getArguments());
        $this->assertEquals('text', $node->getArguments()[0]->getValue());
        $this->assertEquals('image', $node->getArguments()[1]->getValue());
    }

    public function testAnnotationWithDottedName(): void
    {
        $tokens = $this->prepareTokens("@lang.php('int')");
        $parser = new AnnotationParser($tokens);
        $node = $parser->parse();
        $this->assertEquals('lang.php', $node->getName());
        $this->assertCount(1, $node->getArguments());
        $this->assertEquals('int', $node->getArguments()[0]->getValue());
    }

    // --- TypeParser ---

    public function testSimpleType(): void
    {
        $tokens = $this->prepareTokens('int');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(SimpleTypeNode::class, $node);
        $this->assertEquals('int', $node->getName());
    }

    public function testNullableType(): void
    {
        $tokens = $this->prepareTokens('string?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $this->assertInstanceOf(SimpleTypeNode::class, $node->getInnerType());
        $this->assertEquals('string', $node->getInnerType()->getName());
    }

    public function testArrayType(): void
    {
        $tokens = $this->prepareTokens('int[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertInstanceOf(SimpleTypeNode::class, $node->getElementType());
        $this->assertEquals('int', $node->getElementType()->getName());
    }

    public function testNullableArrayType(): void
    {
        $tokens = $this->prepareTokens('Message[]?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $inner = $node->getInnerType();
        $this->assertInstanceOf(ArrayTypeNode::class, $inner);
        $this->assertEquals('Message', $inner->getElementType()->getName());
    }

    public function testUnionType(): void
    {
        $tokens = $this->prepareTokens('Image|User');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(UnionTypeNode::class, $node);
        $types = $node->getTypes();
        $this->assertCount(2, $types);
        $this->assertEquals('Image', $types[0]->getName());
        $this->assertEquals('User', $types[1]->getName());
    }

    public function testInlineObjectType(): void
    {
        $tokens = $this->prepareTokens("{\n  id: int\n  url: string\n}");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $node);
        $props = $node->getProperties();
        $this->assertCount(2, $props);
        $this->assertEquals('id', $props[0]->getName());
        $this->assertEquals('url', $props[1]->getName());
    }

    public function testNamedInlineObjectType(): void
    {
        $tokens = $this->prepareTokens("MyStruct {\n  name: string\n  age: int\n}");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $node);
        $this->assertTrue($node->hasExplicitName());
        $this->assertSame('MyStruct', $node->getExplicitName());
        $props = $node->getProperties();
        $this->assertCount(2, $props);
        $this->assertEquals('name', $props[0]->getName());
        $this->assertEquals('age', $props[1]->getName());
    }

    public function testUnnamedInlineObjectHasNoExplicitName(): void
    {
        $tokens = $this->prepareTokens("{\n  id: int\n}");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $node);
        $this->assertFalse($node->hasExplicitName());
        $this->assertNull($node->getExplicitName());
    }

    public function testBareIdentifierIsStillSimpleType(): void
    {
        $tokens = $this->prepareTokens('User');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(SimpleTypeNode::class, $node);
        $this->assertEquals('User', $node->getName());
    }

    public function testNamedInlineObjectAsArray(): void
    {
        $tokens = $this->prepareTokens("Items {\n  id: int\n}[]");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $inner = $node->getElementType();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $inner);
        $this->assertTrue($inner->hasExplicitName());
        $this->assertSame('Items', $inner->getExplicitName());
    }

    public function testNamedInlineObjectNullable(): void
    {
        $tokens = $this->prepareTokens("Payload {\n  x: int\n}?");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $inner = $node->getInnerType();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $inner);
        $this->assertTrue($inner->hasExplicitName());
        $this->assertSame('Payload', $inner->getExplicitName());
    }

    public function testNamedInlineObjectNullableArray(): void
    {
        $tokens = $this->prepareTokens("Items {\n  id: int\n}[]?");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $arrayType = $node->getInnerType();
        $this->assertInstanceOf(ArrayTypeNode::class, $arrayType);
        $inner = $arrayType->getElementType();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $inner);
        $this->assertTrue($inner->hasExplicitName());
        $this->assertSame('Items', $inner->getExplicitName());
    }

    public function testNamedInlineObjectWithMetadata(): void
    {
        $tokens = $this->prepareTokens("Config {\n  [version] = 1\n  name: string\n}");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $node);
        $this->assertSame('Config', $node->getExplicitName());
        $this->assertCount(1, $node->getMetadata());
        $this->assertCount(1, $node->getProperties());
    }

    public function testNamedInlineObjectWithAnnotationsOnProperties(): void
    {
        $tokens = $this->prepareTokens("Details {\n  @deprecated\n  old_field: string\n  new_field: int\n}");
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(InlineObjectTypeNode::class, $node);
        $this->assertSame('Details', $node->getExplicitName());
        $props = $node->getProperties();
        $this->assertCount(2, $props);
        $this->assertCount(1, $props[0]->getAnnotations());
        $this->assertSame('deprecated', $props[0]->getAnnotations()[0]->getName());
    }

    // --- PropertyDefinitionParser ---

    public function testSimpleProperty(): void
    {
        $tokens = $this->prepareTokens('id: int');
        $parser = new PropertyDefinitionParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(PropertyNode::class, $node);
        $this->assertEquals('id', $node->getName());
        $this->assertFalse($node->isOptional());
        $this->assertInstanceOf(SimpleTypeNode::class, $node->getType());
    }

    public function testOptionalProperty(): void
    {
        $tokens = $this->prepareTokens('avatar_image?: Image');
        $parser = new PropertyDefinitionParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(PropertyNode::class, $node);
        $this->assertEquals('avatar_image', $node->getName());
        $this->assertTrue($node->isOptional());
    }

    public function testNullableProperty(): void
    {
        $tokens = $this->prepareTokens('email: string?');
        $parser = new PropertyDefinitionParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(PropertyNode::class, $node);
        $this->assertEquals('email', $node->getName());
        $this->assertInstanceOf(NullableTypeNode::class, $node->getType());
    }

    // --- NamespaceParser ---

    public function testNamespace(): void
    {
        $scope = $this->parse("ns MappingType {\n  const camelCase\n  const snake_case\n}");
        $namespaces = $scope->getNamespaces();
        $this->assertCount(1, $namespaces);
        $this->assertEquals('MappingType', $namespaces[0]->getName());
        $constants = $namespaces[0]->getConstants();
        $this->assertCount(2, $constants);
        $this->assertEquals('camelCase', $constants[0]->getName());
        $this->assertEquals('snake_case', $constants[1]->getName());
    }

    public function testNamespaceConstantWithoutValue(): void
    {
        $scope = $this->parse("ns Config {\n  const plain\n}");
        $constant = $scope->getNamespaces()[0]->getConstants()[0];
        $this->assertEquals('plain', $constant->getName());
        $this->assertFalse($constant->hasValue());
        $this->assertNull($constant->getValue());
    }

    public function testNamespaceConstantWithNumberValue(): void
    {
        $scope = $this->parse("ns Config {\n  const version = 42\n}");
        $constant = $scope->getNamespaces()[0]->getConstants()[0];
        $this->assertEquals('version', $constant->getName());
        $this->assertTrue($constant->hasValue());
        $this->assertInstanceOf(ValueNode::class, $constant->getValue());
        $this->assertSame(42, $constant->getValue()->getValue());
    }

    public function testNamespaceConstantWithStringValue(): void
    {
        $scope = $this->parse("ns Config {\n  const name = \"hello\"\n}");
        $constant = $scope->getNamespaces()[0]->getConstants()[0];
        $this->assertEquals('name', $constant->getName());
        $this->assertTrue($constant->hasValue());
        $this->assertInstanceOf(ValueNode::class, $constant->getValue());
        $this->assertSame('hello', $constant->getValue()->getValue());
    }

    public function testNamespaceConstantWithIdentifierValue(): void
    {
        $scope = $this->parse("ns Config {\n  const enabled = true\n}");
        $constant = $scope->getNamespaces()[0]->getConstants()[0];
        $this->assertEquals('enabled', $constant->getName());
        $this->assertTrue($constant->hasValue());
        $this->assertInstanceOf(ValueNode::class, $constant->getValue());
        $this->assertSame('true', $constant->getValue()->getValue());
    }

    public function testNamespaceConstantWithReferenceValue(): void
    {
        $scope = $this->parse("ns Config {\n  const alias = Other::value\n}");
        $constant = $scope->getNamespaces()[0]->getConstants()[0];
        $this->assertEquals('alias', $constant->getName());
        $this->assertTrue($constant->hasValue());
        $this->assertInstanceOf(ReferenceNode::class, $constant->getValue());
        $this->assertSame('Other', $constant->getValue()->getNamespace());
        $this->assertSame('value', $constant->getValue()->getConstant());
    }

    public function testNamespaceConstantMixedWithAndWithoutValues(): void
    {
        $scope = $this->parse("ns Config {\n  const plain\n  const version = 2\n  const name = \"hello\"\n}");
        $constants = $scope->getNamespaces()[0]->getConstants();
        $this->assertCount(3, $constants);
        $this->assertFalse($constants[0]->hasValue());
        $this->assertTrue($constants[1]->hasValue());
        $this->assertTrue($constants[2]->hasValue());
    }

    public function testNamespaceConstantFloatValue(): void
    {
        $scope = $this->parse("ns Config {\n  const rate = 3.14\n}");
        $constant = $scope->getNamespaces()[0]->getConstants()[0];
        $this->assertSame(3.14, $constant->getValue()->getValue());
    }

    // --- TypeBlockParser ---

    public function testTypesBlock(): void
    {
        $code = "[type] = {\n  @lang.php('int')\n  int64\n  @lang.php('int')\n  int32\n}";
        $scope = $this->parse($code);
        $aliases = $scope->getTypeAliases();
        $this->assertCount(2, $aliases);
        $this->assertEquals('int64', $aliases[0]->getName());
        $this->assertEquals('int32', $aliases[1]->getName());
        $this->assertCount(1, $aliases[0]->getAnnotations());
        $this->assertEquals('lang.php', $aliases[0]->getAnnotations()[0]->getName());
    }

    public function testTypeBlockUnknownKeywordThrows(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown keyword "bla"');
        $this->parse("[type] = {\n  bla MessageType = 'text'|'image'|'video'\n}");
    }

    public function testTypeBlockPubAlias(): void
    {
        $code = "[type] = {\n  pub MessageType = 'text'|'image'|'video'\n}";
        $scope = $this->parse($code);
        $aliases = $scope->getTypeAliases();
        $this->assertCount(1, $aliases);
        $this->assertEquals('MessageType', $aliases[0]->getName());
        $this->assertTrue($aliases[0]->isPublic());
        $this->assertNotNull($aliases[0]->getTypeDefinition());
    }

    public function testTypeBlockNonPubAlias(): void
    {
        $code = "[type] = {\n  MyType = string\n}";
        $scope = $this->parse($code);
        $aliases = $scope->getTypeAliases();
        $this->assertCount(1, $aliases);
        $this->assertFalse($aliases[0]->isPublic());
    }

    public function testTypeBlockPubBareDeclaration(): void
    {
        $code = "[type] = {\n  pub int64\n}";
        $scope = $this->parse($code);
        $aliases = $scope->getTypeAliases();
        $this->assertCount(1, $aliases);
        $this->assertEquals('int64', $aliases[0]->getName());
        $this->assertTrue($aliases[0]->isPublic());
        $this->assertNull($aliases[0]->getTypeDefinition());
    }

    public function testTypeBlockPubInlineObject(): void
    {
        $code = "[type] = {\n  pub position = {\n    x: int\n    y: int\n  }\n}";
        $scope = $this->parse($code);
        $aliases = $scope->getTypeAliases();
        $this->assertCount(1, $aliases);
        $this->assertEquals('position', $aliases[0]->getName());
        $this->assertTrue($aliases[0]->isPublic());
        $this->assertNotNull($aliases[0]->getTypeDefinition());
    }

    public function testTypeBlockPubWithoutNameThrows(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected type alias name after "pub"');
        $this->parse("[type] = {\n  pub\n}");
    }

    public function testTypeBlockMixedPubAndNonPub(): void
    {
        $code = "[type] = {\n  pub Visible = string\n  hidden = int\n}";
        $scope = $this->parse($code);
        $aliases = $scope->getTypeAliases();
        $this->assertCount(2, $aliases);
        $this->assertTrue($aliases[0]->isPublic());
        $this->assertFalse($aliases[1]->isPublic());
    }

    // --- ModelDefinitionParser ---

    public function testSimpleModel(): void
    {
        $code = "User {\n  id: int\n  name: string\n}";
        $scope = $this->parse($code);
        $models = $scope->getModels();
        $this->assertCount(1, $models);
        $this->assertEquals('User', $models[0]->getName());
        $this->assertCount(2, $models[0]->getProperties());
    }

    public function testModelWithMetadata(): void
    {
        $code = "User {\n  [version] = 2\n  id: int\n}";
        $scope = $this->parse($code);
        $model = $scope->getModels()[0];
        $this->assertCount(1, $model->getMetadata());
        $this->assertEquals('version', $model->getMetadata()[0]->getKey());
        $this->assertEquals(2, $model->getMetadata()[0]->getValue()->getValue());
    }

    public function testModelWithAnnotationsOnProperty(): void
    {
        $code = "User {\n  @local(avatarImageId)\n  avatar_image_id: int?\n}";
        $scope = $this->parse($code);
        $model = $scope->getModels()[0];
        $prop = $model->getProperties()[0];
        $this->assertEquals('avatar_image_id', $prop->getName());
        $this->assertCount(1, $prop->getAnnotations());
        $this->assertEquals('local', $prop->getAnnotations()[0]->getName());
        $this->assertInstanceOf(NullableTypeNode::class, $prop->getType());
    }

    public function testModelWithInlineObject(): void
    {
        $code = "User {\n  avatar_image?: {\n    data: Image?\n  }\n}";
        $scope = $this->parse($code);
        $model = $scope->getModels()[0];
        $prop = $model->getProperties()[0];
        $this->assertEquals('avatar_image', $prop->getName());
        $this->assertTrue($prop->isOptional());
        $this->assertInstanceOf(InlineObjectTypeNode::class, $prop->getType());
        $innerProps = $prop->getType()->getProperties();
        $this->assertCount(1, $innerProps);
        $this->assertEquals('data', $innerProps[0]->getName());
        $this->assertInstanceOf(NullableTypeNode::class, $innerProps[0]->getType());
    }

    // --- Full concept.scsc ---

    public function testConceptFile(): void
    {
        $code = <<<'SCSC'
import scsc/base

[version] = 1
[type] = {

  @lang.php('int')
  @lang.ts('bigint')
  int64

  @lang.php('int')
  @lang.ts('number')
  int32

}

ns MappingType {
  const camelCase
  const snake_case
}

User {
  [version] = 2
  [map:local] = MappingType::camelCase

  // The users ID
  id: int

  @local(avatarImageId)
  avatar_image_id: int?

  avatar_image?: {
    data: Image?
  }

  // the last message the user sent
  last_messages: {
    cached: bool
    data: Message[]
  }
}

Image {
  colors: int[]
  proxy: {
    s1x1: string
  }
}

Message {
  unseen: bool
  text: string
  context: {
    @enum("text", "image")
    type: string
    data: Image|User
  }
}
SCSC;
        $scope = $this->parse($code);

        // Global metadata
        $this->assertCount(1, $scope->getMetadata());
        $this->assertEquals('version', $scope->getMetadata()[0]->getKey());

        // Type aliases
        $aliases = $scope->getTypeAliases();
        $this->assertCount(2, $aliases);
        $this->assertEquals('int64', $aliases[0]->getName());
        $this->assertCount(2, $aliases[0]->getAnnotations());
        $this->assertEquals('int32', $aliases[1]->getName());
        $this->assertCount(2, $aliases[1]->getAnnotations());

        // Namespaces
        $namespaces = $scope->getNamespaces();
        $this->assertCount(1, $namespaces);
        $this->assertEquals('MappingType', $namespaces[0]->getName());
        $this->assertCount(2, $namespaces[0]->getConstants());

        // Models
        $models = $scope->getModels();
        $this->assertCount(3, $models);
        $this->assertEquals('User', $models[0]->getName());
        $this->assertEquals('Image', $models[1]->getName());
        $this->assertEquals('Message', $models[2]->getName());

        // User model details
        $user = $models[0];
        $this->assertCount(2, $user->getMetadata());
        $this->assertCount(4, $user->getProperties());

        // User [map:local] = MappingType::camelCase
        $mapLocal = $user->getMetadata()[1];
        $this->assertEquals('map:local', $mapLocal->getKey());
        $this->assertInstanceOf(ReferenceNode::class, $mapLocal->getValue());

        // Image model
        $image = $models[1];
        $this->assertCount(2, $image->getProperties());
        $colorsType = $image->getProperties()[0]->getType();
        $this->assertInstanceOf(ArrayTypeNode::class, $colorsType);

        // Message.context has union type
        $message = $models[2];
        $context = $message->getProperties()[2];
        $this->assertInstanceOf(InlineObjectTypeNode::class, $context->getType());
        $contextProps = $context->getType()->getProperties();
        $this->assertCount(2, $contextProps);
        $this->assertInstanceOf(UnionTypeNode::class, $contextProps[1]->getType());
        $unionTypes = $contextProps[1]->getType()->getTypes();
        $this->assertCount(2, $unionTypes);
        $this->assertEquals('Image', $unionTypes[0]->getName());
        $this->assertEquals('User', $unionTypes[1]->getName());

        // @enum annotation on context.type
        $this->assertCount(1, $contextProps[0]->getAnnotations());
        $this->assertEquals('enum', $contextProps[0]->getAnnotations()[0]->getName());
    }

    // -------------------------------------------------------
    // ~ Import Parser
    // -------------------------------------------------------

    public function testImportSimple(): void
    {
        $scope = $this->parse("import base");
        $imports = $scope->getImports();
        $this->assertCount(1, $imports);
        $this->assertInstanceOf(ImportNode::class, $imports[0]);
        $this->assertEquals('base', $imports[0]->getPath());
    }

    public function testImportWithPath(): void
    {
        $scope = $this->parse("import scsc/base");
        $imports = $scope->getImports();
        $this->assertCount(1, $imports);
        $this->assertEquals('scsc/base', $imports[0]->getPath());
    }

    public function testImportDeepPath(): void
    {
        $scope = $this->parse("import models/shared/common");
        $imports = $scope->getImports();
        $this->assertCount(1, $imports);
        $this->assertEquals('models/shared/common', $imports[0]->getPath());
    }

    public function testMultipleImports(): void
    {
        $scope = $this->parse("import scsc/base\nimport models/common");
        $imports = $scope->getImports();
        $this->assertCount(2, $imports);
        $this->assertEquals('scsc/base', $imports[0]->getPath());
        $this->assertEquals('models/common', $imports[1]->getPath());
    }

    public function testImportWithModels(): void
    {
        $scope = $this->parse("import scsc/base\n\nUser {\n  id: int\n}");
        $this->assertCount(1, $scope->getImports());
        $this->assertEquals('scsc/base', $scope->getImports()[0]->getPath());
        $this->assertCount(1, $scope->getModels());
        $this->assertEquals('User', $scope->getModels()[0]->getName());
    }

    // -------------------------------------------------------
    // ~ Parenthesized Type Grouping
    // -------------------------------------------------------

    public function testParenthesizedArrayOfNullable(): void
    {
        $tokens = $this->prepareTokens('(int?)[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $inner = $node->getElementType();
        $this->assertInstanceOf(NullableTypeNode::class, $inner);
        $this->assertInstanceOf(SimpleTypeNode::class, $inner->getInnerType());
        $this->assertEquals('int', $inner->getInnerType()->getName());
    }

    public function testParenthesizedNullableUnion(): void
    {
        $tokens = $this->prepareTokens('(string|int)?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $inner = $node->getInnerType();
        $this->assertInstanceOf(UnionTypeNode::class, $inner);
        $types = $inner->getTypes();
        $this->assertCount(2, $types);
        $this->assertEquals('string', $types[0]->getName());
        $this->assertEquals('int', $types[1]->getName());
    }

    public function testNestedParenthesizedGrouping(): void
    {
        $tokens = $this->prepareTokens('((int?)[])[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $inner = $node->getElementType();
        $this->assertInstanceOf(ArrayTypeNode::class, $inner);
        $innerInner = $inner->getElementType();
        $this->assertInstanceOf(NullableTypeNode::class, $innerInner);
        $this->assertEquals('int', $innerInner->getInnerType()->getName());
    }

    public function testParenthesizedIdentitySimpleType(): void
    {
        $tokens = $this->prepareTokens('(int)');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(SimpleTypeNode::class, $node);
        $this->assertEquals('int', $node->getName());
    }

    public function testParenthesizedSimpleWithArraySuffix(): void
    {
        $tokens = $this->prepareTokens('(int)[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertInstanceOf(SimpleTypeNode::class, $node->getElementType());
        $this->assertEquals('int', $node->getElementType()->getName());
    }

    public function testParenthesizedSimpleWithNullableSuffix(): void
    {
        $tokens = $this->prepareTokens('(int)?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $this->assertInstanceOf(SimpleTypeNode::class, $node->getInnerType());
        $this->assertEquals('int', $node->getInnerType()->getName());
    }

    public function testParenthesizedUnionWithArraySuffix(): void
    {
        $tokens = $this->prepareTokens('(string|int)[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $inner = $node->getElementType();
        $this->assertInstanceOf(UnionTypeNode::class, $inner);
        $types = $inner->getTypes();
        $this->assertCount(2, $types);
        $this->assertEquals('string', $types[0]->getName());
        $this->assertEquals('int', $types[1]->getName());
    }

    public function testParenthesizedMultiMemberUnionNullable(): void
    {
        $tokens = $this->prepareTokens('(string|int|bool)?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $inner = $node->getInnerType();
        $this->assertInstanceOf(UnionTypeNode::class, $inner);
        $types = $inner->getTypes();
        $this->assertCount(3, $types);
        $this->assertEquals('string', $types[0]->getName());
        $this->assertEquals('int', $types[1]->getName());
        $this->assertEquals('bool', $types[2]->getName());
    }

    public function testParenthesizedArrayOfNullableWithNullableSuffix(): void
    {
        $tokens = $this->prepareTokens('(int?)[]?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $array = $node->getInnerType();
        $this->assertInstanceOf(ArrayTypeNode::class, $array);
        $nullable = $array->getElementType();
        $this->assertInstanceOf(NullableTypeNode::class, $nullable);
        $this->assertEquals('int', $nullable->getInnerType()->getName());
    }

    public function testParenthesizedArrayInsideArray(): void
    {
        $tokens = $this->prepareTokens('(int[])[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $inner = $node->getElementType();
        $this->assertInstanceOf(ArrayTypeNode::class, $inner);
        $this->assertInstanceOf(SimpleTypeNode::class, $inner->getElementType());
        $this->assertEquals('int', $inner->getElementType()->getName());
    }

    public function testParenthesizedUnionWithArrayMembers(): void
    {
        $tokens = $this->prepareTokens('(string[]|int[])?');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(NullableTypeNode::class, $node);
        $inner = $node->getInnerType();
        $this->assertInstanceOf(UnionTypeNode::class, $inner);
        $types = $inner->getTypes();
        $this->assertCount(2, $types);
        $this->assertInstanceOf(ArrayTypeNode::class, $types[0]);
        $this->assertEquals('string', $types[0]->getElementType()->getName());
        $this->assertInstanceOf(ArrayTypeNode::class, $types[1]);
        $this->assertEquals('int', $types[1]->getElementType()->getName());
    }

    public function testDeeplyNestedParentheses(): void
    {
        $tokens = $this->prepareTokens('(((int)))');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(SimpleTypeNode::class, $node);
        $this->assertEquals('int', $node->getName());
    }

    public function testParenthesizedWithReferenceType(): void
    {
        $tokens = $this->prepareTokens('(User?)[]');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $inner = $node->getElementType();
        $this->assertInstanceOf(NullableTypeNode::class, $inner);
        $this->assertInstanceOf(SimpleTypeNode::class, $inner->getInnerType());
        $this->assertEquals('User', $inner->getInnerType()->getName());
    }

    public function testParenthesizedUnionInUnion(): void
    {
        $tokens = $this->prepareTokens('(string|int)?|bool');
        $parser = new TypeParser($tokens);
        $node = $parser->parse();
        $this->assertInstanceOf(UnionTypeNode::class, $node);
        $types = $node->getTypes();
        $this->assertCount(2, $types);
        $this->assertInstanceOf(NullableTypeNode::class, $types[0]);
        $inner = $types[0]->getInnerType();
        $this->assertInstanceOf(UnionTypeNode::class, $inner);
        $this->assertCount(2, $inner->getTypes());
        $this->assertInstanceOf(SimpleTypeNode::class, $types[1]);
        $this->assertEquals('bool', $types[1]->getName());
    }

    public function testUnmatchedParenThrows(): void
    {
        $this->expectException(ParserException::class);
        $tokens = $this->prepareTokens('(int');
        $parser = new TypeParser($tokens);
        $parser->parse();
    }

    public function testEmptyParensThrows(): void
    {
        $this->expectException(ParserException::class);
        $tokens = $this->prepareTokens('()');
        $parser = new TypeParser($tokens);
        $parser->parse();
    }

    public function testExtraClosingParenThrows(): void
    {
        $this->expectException(ParserException::class);
        $tokens = $this->prepareTokens('(int))');
        $parser = new TypeParser($tokens);
        $parser->parse();
    }
}
