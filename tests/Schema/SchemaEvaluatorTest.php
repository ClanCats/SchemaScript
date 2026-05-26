<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Lexer;
use ClanCats\SchemaScript\ImportLinker;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Schema\SchemaEvaluator;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeKind;
use ClanCats\SchemaScript\SchemaNamespace;
use ClanCats\SchemaScript\Exception\EvaluatorException;

class SchemaEvaluatorTest extends TestCase
{
    private function createNamespaceWithStdlib(): SchemaNamespace
    {
        $ns = new SchemaNamespace();
        $ns->importStdlib();
        return $ns;
    }

    private function evaluateCode(string $code): Definition
    {
        $ns = $this->createNamespaceWithStdlib();
        $code = "import scsc/base\n" . $code;
        $tokens = (new Lexer($code))->tokens();
        $scope = (new ScopeParser($tokens))->parse();
        $linked = (new ImportLinker($ns))->link($scope, $code);
        return (new SchemaEvaluator())->evaluate($linked->getScope(), $linked->getSourceCodeMap());
    }

    private function evaluateCodeStrict(string $code): Definition
    {
        $tokens = (new Lexer($code))->tokens();
        $scope = (new ScopeParser($tokens))->parse();
        return (new SchemaEvaluator())->evaluate($scope);
    }

    private function evaluateConceptFile(): Definition
    {
        $ns = $this->createNamespaceWithStdlib();
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
        $tokens = (new Lexer($code))->tokens();
        $scope = (new ScopeParser($tokens))->parse();
        $linked = (new ImportLinker($ns))->link($scope, $code);
        return (new SchemaEvaluator())->evaluate($linked->getScope(), $linked->getSourceCodeMap());
    }

    public function testGlobalMetadata(): void
    {
        $def = $this->evaluateConceptFile();
        $metadata = $def->getMetadata();

        $this->assertCount(2, $metadata);
        $this->assertSame('version', $metadata[0]->getKey());
        $this->assertSame(1, $metadata[0]->getValue());
        $this->assertTrue($metadata[0]->getAttributes()->isEmpty());
        $this->assertSame('SCSCConfig', $metadata[1]->getKey());
    }

    public function testTypeAliases(): void
    {
        $def = $this->evaluateConceptFile();
        $aliases = $def->getTypeAliases();

        $this->assertArrayHasKey('int64', $aliases);
        $this->assertArrayHasKey('int32', $aliases);

        $this->assertSame('int', $aliases['int64']->getLangType('php'));
        $this->assertSame('bigint', $aliases['int64']->getLangType('ts'));

        $this->assertSame('int', $aliases['int32']->getLangType('php'));
        $this->assertSame('number', $aliases['int32']->getLangType('ts'));
    }

    public function testNamespaces(): void
    {
        $def = $this->evaluateConceptFile();
        $namespaces = $def->getNamespaces();

        $this->assertArrayHasKey('MappingType', $namespaces);
        $this->assertSame([
            'camelCase' => 'MappingType::camelCase',
            'snake_case' => 'MappingType::snake_case',
        ], $namespaces['MappingType']->toArray());
    }

    public function testResolveReference(): void
    {
        $def = $this->evaluateConceptFile();

        $this->assertSame('MappingType::camelCase', $def->resolveReference('MappingType', 'camelCase'));
        $this->assertNull($def->resolveReference('MappingType', 'nonexistent'));
        $this->assertNull($def->resolveReference('Nonexistent', 'camelCase'));
    }

    public function testStructCount(): void
    {
        $def = $this->evaluateConceptFile();
        $structs = $def->getStructs();

        $this->assertCount(8, $structs);
        $this->assertArrayHasKey('User', $structs);
        $this->assertArrayHasKey('UserAvatarImage', $structs);
        $this->assertArrayHasKey('UserLastMessages', $structs);
        $this->assertArrayHasKey('Image', $structs);
        $this->assertArrayHasKey('ImageProxy', $structs);
        $this->assertArrayHasKey('Message', $structs);
        $this->assertArrayHasKey('MessageContext', $structs);
        $this->assertArrayHasKey('map', $structs);
    }

    public function testInlineStructFlag(): void
    {
        $def = $this->evaluateConceptFile();

        $this->assertFalse($def->getStruct('User')->isInline());
        $this->assertFalse($def->getStruct('Image')->isInline());
        $this->assertFalse($def->getStruct('Message')->isInline());

        $this->assertTrue($def->getStruct('UserAvatarImage')->isInline());
        $this->assertTrue($def->getStruct('UserLastMessages')->isInline());
        $this->assertTrue($def->getStruct('ImageProxy')->isInline());
        $this->assertTrue($def->getStruct('MessageContext')->isInline());
    }

    public function testUserStruct(): void
    {
        $def = $this->evaluateConceptFile();
        $user = $def->getStruct('User');

        $this->assertSame(2, $user->getMetadataValue('version'));
        $this->assertSame('MappingType::camelCase', $user->getMetadataValue('map:local'));

        $props = $user->getProperties();
        $this->assertCount(4, $props);

        // id: int
        $this->assertSame('id', $props[0]->getName());
        $this->assertSame(TypeKind::Simple, $props[0]->getType()->getKind());
        $this->assertSame('int', $props[0]->getType()->getName());
        $this->assertFalse($props[0]->isOptional());

        // avatar_image_id: int?
        $this->assertSame('avatar_image_id', $props[1]->getName());
        $this->assertTrue($props[1]->getType()->isNullable());
        $this->assertSame(TypeKind::Simple, $props[1]->getType()->getInnerType()->getKind());
        $this->assertSame('int', $props[1]->getType()->getInnerType()->getName());
        $this->assertFalse($props[1]->isOptional());
        $this->assertTrue($props[1]->hasAnnotation('local'));
        $this->assertSame(['avatarImageId'], $props[1]->getAnnotation('local')->getArguments());

        // avatar_image?: { data: Image? }
        $this->assertSame('avatar_image', $props[2]->getName());
        $this->assertTrue($props[2]->isOptional());
        $this->assertSame(TypeKind::Reference, $props[2]->getType()->getKind());
        $this->assertSame('UserAvatarImage', $props[2]->getType()->getName());

        // last_messages: { cached: bool  data: Message[] }
        $this->assertSame('last_messages', $props[3]->getName());
        $this->assertFalse($props[3]->isOptional());
        $this->assertSame(TypeKind::Reference, $props[3]->getType()->getKind());
        $this->assertSame('UserLastMessages', $props[3]->getType()->getName());
    }

    public function testUserAvatarImageStruct(): void
    {
        $def = $this->evaluateConceptFile();
        $struct = $def->getStruct('UserAvatarImage');

        $props = $struct->getProperties();
        $this->assertCount(1, $props);

        // data: Image?
        $this->assertSame('data', $props[0]->getName());
        $this->assertTrue($props[0]->getType()->isNullable());
        $this->assertSame(TypeKind::Reference, $props[0]->getType()->getInnerType()->getKind());
        $this->assertSame('Image', $props[0]->getType()->getInnerType()->getName());
    }

    public function testUserLastMessagesStruct(): void
    {
        $def = $this->evaluateConceptFile();
        $struct = $def->getStruct('UserLastMessages');

        $props = $struct->getProperties();
        $this->assertCount(2, $props);

        // cached: bool
        $this->assertSame('cached', $props[0]->getName());
        $this->assertSame(TypeKind::Simple, $props[0]->getType()->getKind());
        $this->assertSame('bool', $props[0]->getType()->getName());

        // data: Message[]
        $this->assertSame('data', $props[1]->getName());
        $this->assertSame(TypeKind::Array, $props[1]->getType()->getKind());
        $this->assertSame(TypeKind::Reference, $props[1]->getType()->getInnerType()->getKind());
        $this->assertSame('Message', $props[1]->getType()->getInnerType()->getName());
    }

    public function testImageStruct(): void
    {
        $def = $this->evaluateConceptFile();
        $image = $def->getStruct('Image');

        $props = $image->getProperties();
        $this->assertCount(2, $props);

        // colors: int[]
        $this->assertSame('colors', $props[0]->getName());
        $this->assertSame(TypeKind::Array, $props[0]->getType()->getKind());
        $this->assertSame(TypeKind::Simple, $props[0]->getType()->getInnerType()->getKind());
        $this->assertSame('int', $props[0]->getType()->getInnerType()->getName());

        // proxy: { s1x1: string }
        $this->assertSame('proxy', $props[1]->getName());
        $this->assertSame(TypeKind::Reference, $props[1]->getType()->getKind());
        $this->assertSame('ImageProxy', $props[1]->getType()->getName());
    }

    public function testMessageContextStruct(): void
    {
        $def = $this->evaluateConceptFile();
        $ctx = $def->getStruct('MessageContext');

        $props = $ctx->getProperties();
        $this->assertCount(2, $props);

        // type: string with @enum annotation
        $this->assertSame('type', $props[0]->getName());
        $this->assertSame(TypeKind::Simple, $props[0]->getType()->getKind());
        $this->assertSame('string', $props[0]->getType()->getName());
        $this->assertTrue($props[0]->hasAnnotation('enum'));
        $this->assertSame(['text', 'image'], $props[0]->getAnnotation('enum')->getArguments());

        // data: Image|User
        $this->assertSame('data', $props[1]->getName());
        $this->assertSame(TypeKind::Union, $props[1]->getType()->getKind());
        $unionTypes = $props[1]->getType()->getUnionTypes();
        $this->assertCount(2, $unionTypes);
        $this->assertSame(TypeKind::Reference, $unionTypes[0]->getKind());
        $this->assertSame('Image', $unionTypes[0]->getName());
        $this->assertSame(TypeKind::Reference, $unionTypes[1]->getKind());
        $this->assertSame('User', $unionTypes[1]->getName());
    }

    public function testSimpleModel(): void
    {
        $def = $this->evaluateCode("Simple {\n  name: string\n  age: int\n}");
        $struct = $def->getStruct('Simple');

        $this->assertNotNull($struct);
        $this->assertFalse($struct->isInline());
        $this->assertCount(2, $struct->getProperties());

        $this->assertSame('name', $struct->getProperties()[0]->getName());
        $this->assertSame(TypeKind::Simple, $struct->getProperties()[0]->getType()->getKind());
        $this->assertSame('string', $struct->getProperties()[0]->getType()->getName());
    }

    public function testGetStructReturnsNullForUnknown(): void
    {
        $def = $this->evaluateCode("Foo {\n  x: int\n}");
        $this->assertNull($def->getStruct('Bar'));
    }

    public function testEvaluatorIsReusable(): void
    {
        $ns = $this->createNamespaceWithStdlib();
        $evaluator = new SchemaEvaluator();
        $linker = new ImportLinker($ns);

        $scope1 = (new ScopeParser((new Lexer("import scsc/base\nA {\n  x: int\n}"))->tokens()))->parse();
        $linked1 = $linker->link($scope1);
        $def1 = $evaluator->evaluate($linked1->getScope(), $linked1->getSourceCodeMap());

        $scope2 = (new ScopeParser((new Lexer("import scsc/base\nB {\n  y: string\n}"))->tokens()))->parse();
        $linked2 = $linker->link($scope2);
        $def2 = $evaluator->evaluate($linked2->getScope(), $linked2->getSourceCodeMap());

        $this->assertArrayHasKey('A', $def1->getStructs());
        $this->assertArrayNotHasKey('B', $def1->getStructs());
        $this->assertArrayHasKey('B', $def2->getStructs());
        $this->assertArrayNotHasKey('A', $def2->getStructs());
    }

    public function testDuplicateModelThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate model definition: "Foo"');
        $this->evaluateCode("Foo {\n  x: int\n}\nFoo {\n  y: string\n}");
    }

    public function testTypeAliasModelNameCollisionThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Type alias name collides with model name');
        $this->evaluateCode("[type] = {\n  Foo\n}\nFoo {\n  x: int\n}");
    }

    public function testDuplicateMetadataKeysAllowed(): void
    {
        $def = $this->evaluateCode("[version] = 1\n[version] = 2\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $this->assertCount(3, $metadata);
        $this->assertSame('version', $metadata[0]->getKey());
        $this->assertSame(1, $metadata[0]->getValue());
        $this->assertSame('version', $metadata[1]->getKey());
        $this->assertSame(2, $metadata[1]->getValue());
        $this->assertSame('SCSCConfig', $metadata[2]->getKey());
    }

    public function testDuplicateAnnotationThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate annotation "@local"');
        $this->evaluateCode("Foo {\n  @local(x)\n  @local(y)\n  name: string\n}");
    }

    public function testUnknownNamespaceReferenceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown namespace "Nope"');
        $this->evaluateCode("Foo {\n  [map] = Nope::value\n  x: int\n}");
    }

    public function testUnknownConstantReferenceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown constant "nope" in namespace "Ns"');
        $this->evaluateCode("ns Ns {\n  const a\n}\nFoo {\n  [map] = Ns::nope\n  x: int\n}");
    }

    public function testDuplicatePropertyThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate property "x" in "Foo"');
        $this->evaluateCode("Foo {\n  x: int\n  x: string\n}");
    }

    public function testDuplicateNamespaceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate namespace definition: "Ns"');
        $this->evaluateCode("ns Ns {\n  const a\n}\nns Ns {\n  const b\n}");
    }

    public function testDuplicateConstantInNamespaceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate constant "a" in namespace "Ns"');
        $this->evaluateCode("ns Ns {\n  const a\n  const a\n}");
    }

    public function testDuplicateTypeAliasThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate type alias definition: "mytype"');
        $this->evaluateCode("[type] = {\n  mytype\n  mytype\n}");
    }

    public function testCyclicTypeAliasDirectThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Cyclic type alias detected: A → B → A');
        $this->evaluateCode("[type] = {\n  A = B\n  B = A\n}");
    }

    public function testCyclicTypeAliasIndirectThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Cyclic type alias detected');
        $this->evaluateCode("[type] = {\n  A = B\n  B = C\n  C = A\n}");
    }

    public function testCyclicTypeAliasSelfReferenceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Cyclic type alias detected: A → A');
        $this->evaluateCode("[type] = {\n  A = A\n}");
    }

    // -------------------------------------------------------
    // ~ Metadata Structure Tests
    // -------------------------------------------------------

    public function testMetadataObjectBlock(): void
    {
        $def = $this->evaluateCode("[config] = {\n  foo = 'bar'\n  num = 42\n}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $this->assertCount(2, $metadata);
        $this->assertSame('config', $metadata[0]->getKey());
        $this->assertIsArray($metadata[0]->getValue());
        $entries = $metadata[0]->getValue();
        $this->assertCount(2, $entries);
        $this->assertSame('foo', $entries[0]->getKey());
        $this->assertSame('bar', $entries[0]->getValue());
        $this->assertSame('num', $entries[1]->getKey());
        $this->assertSame(42, $entries[1]->getValue());
    }

    public function testMetadataList(): void
    {
        $def = $this->evaluateCode("[colors] = {'red', 'green', 'blue'}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $this->assertSame('colors', $metadata[0]->getKey());
        $this->assertSame(['red', 'green', 'blue'], $metadata[0]->getValue());
    }

    public function testMetadataBoolean(): void
    {
        $def = $this->evaluateCode("[enabled] = true\n[disabled] = false\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $this->assertTrue($metadata[0]->getValue());
        $this->assertFalse($metadata[1]->getValue());
    }

    public function testMetadataNestedBlock(): void
    {
        $def = $this->evaluateCode("[outer] = {\n  inner = {\n    value = 'deep'\n  }\n}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $outer = $metadata[0]->getValue();
        $this->assertCount(1, $outer);
        $inner = $outer[0]->getValue();
        $this->assertCount(1, $inner);
        $this->assertSame('value', $inner[0]->getKey());
        $this->assertSame('deep', $inner[0]->getValue());
    }

    public function testMetadataWithAnnotatedEntry(): void
    {
        $def = $this->evaluateCode("[meta] = {\n  @a('example')\n  value = 'fooo'\n}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $entries = $metadata[0]->getValue();
        $this->assertCount(1, $entries);
        $this->assertSame('value', $entries[0]->getKey());
        $this->assertSame('fooo', $entries[0]->getValue());
        $this->assertTrue($entries[0]->getAttributes()->has('a'));
        $this->assertSame(['example'], $entries[0]->getAttributes()->get('a')->getArguments());
    }

    public function testMetadataStandaloneIdentifier(): void
    {
        $def = $this->evaluateCode("[meta] = {\n  @a('x')\n  someId\n}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $entries = $metadata[0]->getValue();
        $this->assertCount(1, $entries);
        $this->assertSame('someId', $entries[0]->getKey());
        $this->assertNull($entries[0]->getValue());
        $this->assertTrue($entries[0]->getAttributes()->has('a'));
    }

    public function testMetadataWithMetadataKeyEntries(): void
    {
        $def = $this->evaluateCode("[block] = {\n  [keyA] = 'val1'\n  [keyB] = 'val2'\n}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $entries = $metadata[0]->getValue();
        $this->assertCount(2, $entries);
        $this->assertSame('keyA', $entries[0]->getKey());
        $this->assertSame('val1', $entries[0]->getValue());
        $this->assertSame('keyB', $entries[1]->getKey());
        $this->assertSame('val2', $entries[1]->getValue());
    }

    public function testModelMetadataBlock(): void
    {
        $def = $this->evaluateCode("Foo {\n  [alias] = {\n    frontend = 'Account'\n    backend = 'User'\n  }\n  x: int\n}");
        $metadata = $def->getStruct('Foo')->getMetadata();
        $this->assertCount(1, $metadata);
        $entries = $metadata[0]->getValue();
        $this->assertCount(2, $entries);
        $this->assertSame('frontend', $entries[0]->getKey());
        $this->assertSame('Account', $entries[0]->getValue());
    }

    public function testMetadataEntryStructure(): void
    {
        $def = $this->evaluateCode("[key] = 'val'\nFoo {\n  x: int\n}");
        $entry = $def->getMetadata()[0];
        $this->assertInstanceOf(\ClanCats\SchemaScript\Schema\MetadataEntry::class, $entry);
        $this->assertSame('key', $entry->getKey());
        $this->assertSame('val', $entry->getValue());
        $this->assertTrue($entry->getAttributes()->isEmpty());
    }

    public function testMetadataListWithNumbers(): void
    {
        $def = $this->evaluateCode("[nums] = {1, 2, 3}\nFoo {\n  x: int\n}");
        $this->assertSame([1, 2, 3], $def->getMetadata()[0]->getValue());
    }

    public function testMetadataListWithMixedTypes(): void
    {
        $def = $this->evaluateCode("[mix] = {'text', 42, 3.14}\nFoo {\n  x: int\n}");
        $this->assertSame(['text', 42, 3.14], $def->getMetadata()[0]->getValue());
    }

    public function testMetadataBlockAllTypes(): void
    {
        $def = $this->evaluateCode("[all] = {\n  str = 'hello'\n  num = 42\n  flt = 3.14\n  yes = true\n  no = false\n  arr = {'a', 'b'}\n  obj = {\n    nested = 'val'\n  }\n}\nFoo {\n  x: int\n}");
        $metadata = $def->getMetadata();
        $entries = $metadata[0]->getValue();
        $this->assertCount(7, $entries);

        $this->assertSame('str', $entries[0]->getKey());
        $this->assertSame('hello', $entries[0]->getValue());

        $this->assertSame('num', $entries[1]->getKey());
        $this->assertSame(42, $entries[1]->getValue());

        $this->assertSame('flt', $entries[2]->getKey());
        $this->assertSame(3.14, $entries[2]->getValue());

        $this->assertSame('yes', $entries[3]->getKey());
        $this->assertTrue($entries[3]->getValue());

        $this->assertSame('no', $entries[4]->getKey());
        $this->assertFalse($entries[4]->getValue());

        $this->assertSame('arr', $entries[5]->getKey());
        $this->assertSame(['a', 'b'], $entries[5]->getValue());

        $this->assertSame('obj', $entries[6]->getKey());
        $this->assertIsArray($entries[6]->getValue());
        $this->assertCount(1, $entries[6]->getValue());
        $this->assertSame('nested', $entries[6]->getValue()[0]->getKey());
        $this->assertSame('val', $entries[6]->getValue()[0]->getValue());
    }

    public function testMetadataThreeLevelNesting(): void
    {
        $def = $this->evaluateCode("[root] = {\n  l1 = {\n    l2 = {\n      l3 = 'deep'\n    }\n  }\n}\nFoo {\n  x: int\n}");
        $root = $def->getMetadata()[0]->getValue();
        $l1 = $root[0]->getValue();
        $l2 = $l1[0]->getValue();
        $this->assertSame('l3', $l2[0]->getKey());
        $this->assertSame('deep', $l2[0]->getValue());
    }

    public function testMetadataAnnotatedEntryAttributes(): void
    {
        $def = $this->evaluateCode("[meta] = {\n  @first\n  @second('a', 'b')\n  entry = 'val'\n}\nFoo {\n  x: int\n}");
        $entry = $def->getMetadata()[0]->getValue()[0];
        $this->assertSame('entry', $entry->getKey());
        $this->assertSame('val', $entry->getValue());
        $this->assertTrue($entry->getAttributes()->has('first'));
        $this->assertSame([], $entry->getAttributes()->get('first')->getArguments());
        $this->assertTrue($entry->getAttributes()->has('second'));
        $this->assertSame(['a', 'b'], $entry->getAttributes()->get('second')->getArguments());
    }

    public function testMetadataStandaloneIdentifierEvaluated(): void
    {
        $def = $this->evaluateCode("[flags] = {\n  @tag('x')\n  myFlag\n  @other\n  myOther\n  bare\n}\nFoo {\n  x: int\n}");
        $entries = $def->getMetadata()[0]->getValue();
        $this->assertCount(3, $entries);

        $this->assertSame('myFlag', $entries[0]->getKey());
        $this->assertNull($entries[0]->getValue());
        $this->assertTrue($entries[0]->getAttributes()->has('tag'));
        $this->assertSame(['x'], $entries[0]->getAttributes()->get('tag')->getArguments());

        $this->assertSame('myOther', $entries[1]->getKey());
        $this->assertNull($entries[1]->getValue());
        $this->assertTrue($entries[1]->getAttributes()->has('other'));
        $this->assertSame([], $entries[1]->getAttributes()->get('other')->getArguments());

        $this->assertSame('bare', $entries[2]->getKey());
        $this->assertNull($entries[2]->getValue());
        $this->assertTrue($entries[2]->getAttributes()->isEmpty());
    }

    public function testMetadataDuplicateBlockKeys(): void
    {
        $def = $this->evaluateCode("[gen] = {\n  [php.mappers] = {\n    version = 1\n  }\n  [php.mappers] = {\n    version = 2\n  }\n}\nFoo {\n  x: int\n}");
        $entries = $def->getMetadata()[0]->getValue();
        $this->assertCount(2, $entries);
        $this->assertSame('php.mappers', $entries[0]->getKey());
        $this->assertSame('php.mappers', $entries[1]->getKey());
        $this->assertSame(1, $entries[0]->getValue()[0]->getValue());
        $this->assertSame(2, $entries[1]->getValue()[0]->getValue());
    }

    public function testMetadataMixedEntryStyles(): void
    {
        $def = $this->evaluateCode("[mix] = {\n  [bracket] = 'a'\n  plain = 'b'\n  @tag\n  standaloneId\n}\nFoo {\n  x: int\n}");
        $entries = $def->getMetadata()[0]->getValue();
        $this->assertCount(3, $entries);
        $this->assertSame('bracket', $entries[0]->getKey());
        $this->assertSame('a', $entries[0]->getValue());
        $this->assertSame('plain', $entries[1]->getKey());
        $this->assertSame('b', $entries[1]->getValue());
        $this->assertSame('standaloneId', $entries[2]->getKey());
        $this->assertNull($entries[2]->getValue());
        $this->assertTrue($entries[2]->getAttributes()->has('tag'));
    }

    public function testMetadataEmptyBlockEvaluated(): void
    {
        $def = $this->evaluateCode("[empty] = {}\nFoo {\n  x: int\n}");
        $this->assertSame([], $def->getMetadata()[0]->getValue());
    }

    public function testMetadataReferenceInBlock(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const mode = 'debug'\n}\n[settings] = {\n  mode = Config::mode\n}\nFoo {\n  x: int\n}");
        $entries = $def->getMetadata()[0]->getValue();
        $this->assertSame('mode', $entries[0]->getKey());
        $this->assertSame('debug', $entries[0]->getValue());
    }

    public function testMetadataReferenceValuelessInBlock(): void
    {
        $def = $this->evaluateCode("ns Flags {\n  const enabled\n}\n[settings] = {\n  flag = Flags::enabled\n}\nFoo {\n  x: int\n}");
        $entries = $def->getMetadata()[0]->getValue();
        $this->assertSame('flag', $entries[0]->getKey());
        $this->assertSame('Flags::enabled', $entries[0]->getValue());
    }

    public function testGetMetadataValueSearchesEntries(): void
    {
        $def = $this->evaluateCode("Foo {\n  [version] = 42\n  [name] = 'test'\n  x: int\n}");
        $struct = $def->getStruct('Foo');
        $this->assertSame(42, $struct->getMetadataValue('version'));
        $this->assertSame('test', $struct->getMetadataValue('name'));
        $this->assertNull($struct->getMetadataValue('nonexistent'));
    }

    public function testGetMetadataValueReturnsFirstMatch(): void
    {
        $def = $this->evaluateCode("Foo {\n  [ver] = 1\n  [ver] = 2\n  x: int\n}");
        $struct = $def->getStruct('Foo');
        $this->assertSame(1, $struct->getMetadataValue('ver'));
    }

    public function testMetadataNestedBlockWithListsEvaluated(): void
    {
        $def = $this->evaluateCode("[config] = {\n  tags = {'a', 'b'}\n  nested = {\n    nums = {1, 2}\n  }\n}\nFoo {\n  x: int\n}");
        $entries = $def->getMetadata()[0]->getValue();
        $this->assertCount(2, $entries);

        $this->assertSame('tags', $entries[0]->getKey());
        $this->assertSame(['a', 'b'], $entries[0]->getValue());

        $this->assertSame('nested', $entries[1]->getKey());
        $innerEntries = $entries[1]->getValue();
        $this->assertCount(1, $innerEntries);
        $this->assertSame('nums', $innerEntries[0]->getKey());
        $this->assertSame([1, 2], $innerEntries[0]->getValue());
    }

    public function testModelMetadataWithReferenceEvaluated(): void
    {
        $def = $this->evaluateCode("ns Mapping {\n  const camel\n}\nFoo {\n  [style] = Mapping::camel\n  [config] = {\n    ref = Mapping::camel\n  }\n  x: int\n}");
        $struct = $def->getStruct('Foo');
        $this->assertSame('Mapping::camel', $struct->getMetadataValue('style'));
        $configEntries = $struct->getMetadata()[1]->getValue();
        $this->assertSame('Mapping::camel', $configEntries[0]->getValue());
    }

    public function testInlineObjectMetadataEvaluated(): void
    {
        $def = $this->evaluateCode("Foo {\n  config: AppConfig {\n    [version] = 1\n    [debug] = true\n    name: string\n  }\n}");
        $struct = $def->getStruct('AppConfig');
        $this->assertNotNull($struct);
        $metadata = $struct->getMetadata();
        $this->assertCount(2, $metadata);
        $this->assertSame('version', $metadata[0]->getKey());
        $this->assertSame(1, $metadata[0]->getValue());
        $this->assertSame('debug', $metadata[1]->getKey());
        $this->assertTrue($metadata[1]->getValue());
    }

    public function testIntegrationSchemaMetadata(): void
    {
        $ns = $this->createNamespaceWithStdlib();
        $code = file_get_contents(__DIR__ . '/../../integration/SCHEMA.scsc');
        $tokens = (new \ClanCats\SchemaScript\Lexer($code))->tokens();
        $scope = (new \ClanCats\SchemaScript\Parser\ScopeParser($tokens))->parse();
        $linked = (new ImportLinker($ns))->link($scope, $code);
        $def = (new SchemaEvaluator())->evaluate($linked->getScope(), $linked->getSourceCodeMap());

        $metadata = $def->getMetadata();
        $this->assertCount(4, $metadata);

        $this->assertSame('version', $metadata[0]->getKey());
        $this->assertSame(1, $metadata[0]->getValue());

        $this->assertSame('map', $metadata[1]->getKey());

        $this->assertSame('generate', $metadata[2]->getKey());
        $generate = $metadata[2]->getValue();
        $this->assertCount(3, $generate);

        $this->assertSame('php.mappers', $generate[0]->getKey());
        $v1 = $generate[0]->getValue();
        $this->assertSame('output', $v1[0]->getKey());
        $this->assertSame('output/php/Mappers/', $v1[0]->getValue());
        $this->assertSame('namespace', $v1[1]->getKey());
        $this->assertSame('IntegrationEx\\Mappers\\', $v1[1]->getValue());

        $this->assertSame('php.samg', $generate[1]->getKey());
        $v3 = $generate[1]->getValue();
        $this->assertSame('output', $v3[0]->getKey());
        $this->assertSame('output/php/SAMG/', $v3[0]->getValue());
        $this->assertSame('namespace', $v3[1]->getKey());
        $this->assertSame('IntegrationEx\\SAMG\\', $v3[1]->getValue());

        $this->assertSame('ts.types', $generate[2]->getKey());
        $v2 = $generate[2]->getValue();
        $this->assertSame('output', $v2[0]->getKey());
        $this->assertSame('output/ts/types/', $v2[0]->getValue());
    }

    // -------------------------------------------------------
    // ~ Type Validation Tests
    // -------------------------------------------------------

    public function testUnknownTypeThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "strng"');
        $this->evaluateCode("Foo {\n  name: strng\n}");
    }

    public function testUnknownTypeWithoutNamespaceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "int"');
        $this->evaluateCodeStrict("Foo {\n  id: int\n}");
    }

    public function testUnknownTypeInArrayThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "strng"');
        $this->evaluateCode("Foo {\n  names: strng[]\n}");
    }

    public function testUnknownTypeInNullableThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "strng"');
        $this->evaluateCode("Foo {\n  name: strng?\n}");
    }

    public function testUnknownTypeInUnionThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "strng"');
        $this->evaluateCode("Foo {\n  value: int|strng\n}");
    }

    public function testUnknownTypeInNullableArrayThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "strng"');
        $this->evaluateCode("Foo {\n  names: strng[]?\n}");
    }

    public function testUnknownTypeInInlineObjectThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "strng"');
        $this->evaluateCode("Foo {\n  data: {\n    name: strng\n  }\n}");
    }

    public function testLocalTypeAliasIsValid(): void
    {
        $def = $this->evaluateCode("[type] = {\n  custom_id\n}\nFoo {\n  id: custom_id\n}");
        $this->assertSame(TypeKind::Simple, $def->getStruct('Foo')->getProperties()[0]->getType()->getKind());
        $this->assertSame('custom_id', $def->getStruct('Foo')->getProperties()[0]->getType()->getName());
    }

    public function testAnnotatedTypeAliasIsAlias(): void
    {
        $def = $this->evaluateCode("[type] = {\n  @lang.php('int')\n  myint\n}\nFoo {\n  id: myint\n}");
        $this->assertSame(TypeKind::Simple, $def->getStruct('Foo')->getProperties()[0]->getType()->getKind());
        $this->assertSame('myint', $def->getStruct('Foo')->getProperties()[0]->getType()->getName());
    }

    public function testStdlibBuiltinTypesAreAlias(): void
    {
        $def = $this->evaluateCode("Foo {\n  a: int\n  b: string\n  c: bool\n  d: float\n}");
        $props = $def->getStruct('Foo')->getProperties();
        foreach ($props as $prop) {
            $this->assertSame(TypeKind::Simple, $prop->getType()->getKind(),
                sprintf('Expected "%s" to be KIND_ALIAS', $prop->getName()));
        }
    }

    public function testStdlibTypesInCompositeExpressions(): void
    {
        $def = $this->evaluateCode("Foo {\n  a: int[]\n  b: string?\n  c: int|string\n  d: float[]?\n}");
        $props = $def->getStruct('Foo')->getProperties();

        $this->assertTrue($props[0]->getType()->isArray());
        $this->assertSame('int', $props[0]->getType()->getInnerType()->getName());

        $this->assertTrue($props[1]->getType()->isNullable());
        $this->assertSame('string', $props[1]->getType()->getInnerType()->getName());

        $this->assertTrue($props[2]->getType()->isUnion());
        $this->assertSame('int', $props[2]->getType()->getUnionTypes()[0]->getName());
        $this->assertSame('string', $props[2]->getType()->getUnionTypes()[1]->getName());

        $this->assertTrue($props[3]->getType()->isNullable());
        $this->assertTrue($props[3]->getType()->getInnerType()->isArray());
        $this->assertSame('float', $props[3]->getType()->getInnerType()->getInnerType()->getName());
    }

    public function testModelReferenceStillWorksWithValidation(): void
    {
        $def = $this->evaluateCode("Bar {\n  x: int\n}\nFoo {\n  bar: Bar\n}");
        $this->assertSame(TypeKind::Reference, $def->getStruct('Foo')->getProperties()[0]->getType()->getKind());
        $this->assertSame('Bar', $def->getStruct('Foo')->getProperties()[0]->getType()->getName());
    }

    public function testLocalTypeOverridesImportedType(): void
    {
        $def = $this->evaluateCode("[type] = {\n  @lang.php('int')\n  @lang.ts('bigint')\n  int64\n}\nFoo {\n  id: int64\n}");
        $this->assertSame(TypeKind::Simple, $def->getStruct('Foo')->getProperties()[0]->getType()->getKind());
        $aliases = $def->getTypeAliases();
        $this->assertArrayHasKey('int64', $aliases);
        $this->assertSame('int', $aliases['int64']->getLangType('php'));
    }

    public function testAllStdlibTypesResolvable(): void
    {
        $allTypes = [
            'int', 'int8', 'int16', 'int32', 'int64',
            'uint', 'uint8', 'uint16', 'uint32', 'uint64',
            'float', 'float32', 'float64', 'double',
            'string', 'bool',
        ];

        $props = implode("\n  ", array_map(fn($t) => "p_{$t}: {$t}", $allTypes));
        $def = $this->evaluateCode("Foo {\n  {$props}\n}");
        $struct = $def->getStruct('Foo');
        $this->assertCount(count($allTypes), $struct->getProperties());

        foreach ($struct->getProperties() as $i => $prop) {
            $this->assertSame(TypeKind::Simple, $prop->getType()->getKind(),
                sprintf('Type "%s" should be KIND_ALIAS', $allTypes[$i]));
            $this->assertSame($allTypes[$i], $prop->getType()->getName());
        }
    }

    public function testErrorMessageSuggestsImportOrTypes(): void
    {
        try {
            $this->evaluateCode("Foo {\n  name: unknown_type\n}");
            $this->fail('Expected EvaluatorException');
        } catch (EvaluatorException $e) {
            $this->assertStringContainsString('Unknown type "unknown_type"', $e->getMessage());
            $this->assertStringContainsString('import', $e->getMessage());
            $this->assertStringContainsString('[type]', $e->getMessage());
        }
    }

    public function testUnknownTypeSecondPropertyThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "nope"');
        $this->evaluateCode("Foo {\n  name: string\n  age: nope\n}");
    }

    public function testUnknownTypeInUnionSecondBranchThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Unknown type "nope"');
        $this->evaluateCode("Bar {\n  x: int\n}\nFoo {\n  data: Bar|nope\n}");
    }

    public function testStrictModeRejectsAllBarePrimitives(): void
    {
        $primitives = ['int', 'string', 'bool', 'float'];
        foreach ($primitives as $type) {
            try {
                $this->evaluateCodeStrict("Foo {\n  x: {$type}\n}");
                $this->fail("Expected EvaluatorException for type \"{$type}\"");
            } catch (EvaluatorException $e) {
                $this->assertStringContainsString("Unknown type \"{$type}\"", $e->getMessage());
            }
        }
    }

    public function testLocalTypesBlockMakesTypeValid(): void
    {
        $def = $this->evaluateCodeStrict("[type] = {\n  mytype\n}\nFoo {\n  x: mytype\n}");
        $this->assertSame('mytype', $def->getStruct('Foo')->getProperties()[0]->getType()->getName());
        $this->assertSame(TypeKind::Simple, $def->getStruct('Foo')->getProperties()[0]->getType()->getKind());
    }

    public function testModelMetadataReferenceWorks(): void
    {
        $def = $this->evaluateCode("ns Mapping {\n  const camel\n}\nFoo {\n  [style] = Mapping::camel\n  x: int\n}");
        $this->assertSame('Mapping::camel', $def->getStruct('Foo')->getMetadataValue('style'));
    }

    public function testValuelessConstantResolvesToQualifiedName(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const plain\n}");
        $this->assertSame('Config::plain', $def->getNamespaces()['Config']->getConstantValue('plain'));
    }

    public function testConstantWithNumericValue(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const version = 42\n}");
        $this->assertSame(42, $def->getNamespaces()['Config']->getConstantValue('version'));
    }

    public function testConstantWithStringValue(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const endpoint = \"/api/v2\"\n}");
        $this->assertSame('/api/v2', $def->getNamespaces()['Config']->getConstantValue('endpoint'));
    }

    public function testConstantWithIdentifierValue(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const enabled = true\n}");
        $this->assertSame('true', $def->getNamespaces()['Config']->getConstantValue('enabled'));
    }

    public function testConstantWithReferenceValue(): void
    {
        $def = $this->evaluateCode("ns Source {\n  const original = 99\n}\nns Config {\n  const alias = Source::original\n}");
        $this->assertSame(99, $def->getNamespaces()['Config']->getConstantValue('alias'));
    }

    public function testValuedConstantReferenceInMetadata(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const version = 42\n}\nFoo {\n  [ver] = Config::version\n  x: int\n}");
        $this->assertSame(42, $def->getStruct('Foo')->getMetadataValue('ver'));
    }

    public function testResolveReferenceReturnsValueForValuedConstant(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const version = 42\n}");
        $this->assertSame(42, $def->resolveReference('Config', 'version'));
    }

    public function testResolveReferenceReturnsQualifiedNameForValuelessConstant(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const plain\n}");
        $this->assertSame('Config::plain', $def->resolveReference('Config', 'plain'));
    }

    public function testMixedValuedAndValuelessConstants(): void
    {
        $def = $this->evaluateCode("ns Config {\n  const plain\n  const version = 2\n  const name = \"hello\"\n}");
        $ns = $def->getNamespaces()['Config'];
        $this->assertSame('Config::plain', $ns->getConstantValue('plain'));
        $this->assertSame(2, $ns->getConstantValue('version'));
        $this->assertSame('hello', $ns->getConstantValue('name'));
    }

    public function testConstantReferenceToValuelessConstantResolvesToQualifiedName(): void
    {
        $def = $this->evaluateCode("ns Source {\n  const symbol\n}\nns Config {\n  const ref = Source::symbol\n}");
        $this->assertSame('Source::symbol', $def->getNamespaces()['Config']->getConstantValue('ref'));
    }

    public function testTypeConvenienceMethods(): void
    {
        $def = $this->evaluateCode("Ref {\n  x: int\n}\nFoo {\n  a: int\n  b: Ref\n  c: int[]\n  d: int?\n  e: int|string\n}");
        $props = $def->getStruct('Foo')->getProperties();

        $this->assertTrue($props[0]->getType()->isSimple());
        $this->assertTrue($props[1]->getType()->isReference());
        $this->assertTrue($props[2]->getType()->isArray());
        $this->assertTrue($props[3]->getType()->isNullable());
        $this->assertTrue($props[4]->getType()->isUnion());
    }

    public function testExplicitInlineObjectName(): void
    {
        $def = $this->evaluateCode("Foo {\n  data: MyData {\n    name: string\n    age: int\n  }\n}");
        $this->assertNotNull($def->getStruct('MyData'));
        $this->assertTrue($def->getStruct('MyData')->isInline());

        $props = $def->getStruct('MyData')->getProperties();
        $this->assertCount(2, $props);
        $this->assertSame('name', $props[0]->getName());
        $this->assertSame('age', $props[1]->getName());

        $fooProps = $def->getStruct('Foo')->getProperties();
        $this->assertSame(TypeKind::Reference, $fooProps[0]->getType()->getKind());
        $this->assertSame('MyData', $fooProps[0]->getType()->getName());
    }

    public function testExplicitInlineNameCollisionWithModelThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Inline object struct name collision: "Bar" is already defined');
        $this->evaluateCode("Bar {\n  x: int\n}\nFoo {\n  data: Bar {\n    y: string\n  }\n}");
    }

    public function testExplicitInlineNameAsArray(): void
    {
        $def = $this->evaluateCode("Foo {\n  items: ItemList {\n    id: int\n  }[]\n}");
        $this->assertNotNull($def->getStruct('ItemList'));
        $this->assertTrue($def->getStruct('ItemList')->isInline());

        $fooProps = $def->getStruct('Foo')->getProperties();
        $this->assertSame(TypeKind::Array, $fooProps[0]->getType()->getKind());
        $this->assertSame(TypeKind::Reference, $fooProps[0]->getType()->getInnerType()->getKind());
        $this->assertSame('ItemList', $fooProps[0]->getType()->getInnerType()->getName());
    }

    public function testExplicitInlineNameNullable(): void
    {
        $def = $this->evaluateCode("Foo {\n  payload: Payload {\n    x: int\n  }?\n}");
        $this->assertNotNull($def->getStruct('Payload'));
        $this->assertTrue($def->getStruct('Payload')->isInline());

        $fooProps = $def->getStruct('Foo')->getProperties();
        $this->assertSame(TypeKind::Nullable, $fooProps[0]->getType()->getKind());
        $this->assertSame(TypeKind::Reference, $fooProps[0]->getType()->getInnerType()->getKind());
        $this->assertSame('Payload', $fooProps[0]->getType()->getInnerType()->getName());
    }

    public function testExplicitInlineNameNullableArray(): void
    {
        $def = $this->evaluateCode("Foo {\n  items: ItemList {\n    id: int\n  }[]?\n}");

        $fooProps = $def->getStruct('Foo')->getProperties();
        $this->assertSame(TypeKind::Nullable, $fooProps[0]->getType()->getKind());
        $this->assertSame(TypeKind::Array, $fooProps[0]->getType()->getInnerType()->getKind());
        $this->assertSame('ItemList', $fooProps[0]->getType()->getInnerType()->getInnerType()->getName());
    }

    public function testExplicitInlineNameWithMetadata(): void
    {
        $def = $this->evaluateCode("Foo {\n  config: AppConfig {\n    [version] = 1\n    name: string\n  }\n}");
        $struct = $def->getStruct('AppConfig');
        $this->assertNotNull($struct);
        $this->assertSame(1, $struct->getMetadataValue('version'));
        $this->assertCount(1, $struct->getProperties());
        $this->assertSame('name', $struct->getProperties()[0]->getName());
    }

    public function testExplicitInlineNameWithAnnotations(): void
    {
        $def = $this->evaluateCode("Foo {\n  detail: Detail {\n    @deprecated\n    old: string\n    new_field: int\n  }\n}");
        $struct = $def->getStruct('Detail');
        $this->assertNotNull($struct);
        $this->assertCount(2, $struct->getProperties());
        $this->assertTrue($struct->getProperties()[0]->hasAnnotation('deprecated'));
    }

    public function testExplicitInlineNestedInsideExplicitInline(): void
    {
        $def = $this->evaluateCode("Foo {\n  outer: Outer {\n    inner: Inner {\n      val: int\n    }\n  }\n}");
        $this->assertNotNull($def->getStruct('Outer'));
        $this->assertNotNull($def->getStruct('Inner'));
        $this->assertTrue($def->getStruct('Outer')->isInline());
        $this->assertTrue($def->getStruct('Inner')->isInline());

        $outerProps = $def->getStruct('Outer')->getProperties();
        $this->assertSame(TypeKind::Reference, $outerProps[0]->getType()->getKind());
        $this->assertSame('Inner', $outerProps[0]->getType()->getName());

        $innerProps = $def->getStruct('Inner')->getProperties();
        $this->assertSame('val', $innerProps[0]->getName());
    }

    public function testExplicitInlineNameCollisionBetweenTwoInlinesThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Inline object struct name collision: "Dup" is already defined');
        $this->evaluateCode("Foo {\n  a: Dup {\n    x: int\n  }\n  b: Dup {\n    y: string\n  }\n}");
    }

    public function testMultipleDistinctExplicitInlineNames(): void
    {
        $def = $this->evaluateCode("Foo {\n  a: Alpha {\n    x: int\n  }\n  b: Beta {\n    y: string\n  }\n}");
        $this->assertNotNull($def->getStruct('Alpha'));
        $this->assertNotNull($def->getStruct('Beta'));
        $this->assertSame('x', $def->getStruct('Alpha')->getProperties()[0]->getName());
        $this->assertSame('y', $def->getStruct('Beta')->getProperties()[0]->getName());
    }

    public function testMixedExplicitAndAutoNamedInlines(): void
    {
        $def = $this->evaluateCode("Foo {\n  named: MyNamed {\n    x: int\n  }\n  auto: {\n    y: string\n  }\n}");
        $this->assertNotNull($def->getStruct('MyNamed'));
        $this->assertNotNull($def->getStruct('FooAuto'));
        $this->assertTrue($def->getStruct('MyNamed')->isInline());
        $this->assertTrue($def->getStruct('FooAuto')->isInline());
    }

    // -------------------------------------------------------
    // ~ Import Tests
    // -------------------------------------------------------

    private function fixtureDir(): string
    {
        return __DIR__ . '/../fixtures/imports';
    }

    private function evaluateWithImports(string $code): Definition
    {
        $ns = new SchemaNamespace();
        $ns->importStdlib();
        $ns->importDirectory($this->fixtureDir());

        $tokens = (new Lexer($code))->tokens();
        $scope = (new ScopeParser($tokens))->parse();
        $linked = (new ImportLinker($ns))->link($scope, $code);
        return (new SchemaEvaluator())->evaluate($linked->getScope(), $linked->getSourceCodeMap());
    }

    public function testBasicImport(): void
    {
        $def = $this->evaluateWithImports("import models/post\n\nUser {\n  id: int\n}");
        $this->assertNotNull($def->getStruct('User'));
        $this->assertNotNull($def->getStruct('Post'));
    }

    public function testTransitiveImport(): void
    {
        $def = $this->evaluateWithImports("import models/user");
        $this->assertNotNull($def->getStruct('User'));
        $this->assertNotNull($def->getStruct('Timestamps'));
    }

    public function testCircularImportThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Circular import detected');
        $this->evaluateWithImports("import circular_a");
    }

    public function testCircularDirectSelfImportThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Circular import detected');
        $this->evaluateWithImports("import circular_direct");
    }

    public function testImportWithoutNamespaceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('no SchemaNamespace provided');
        $tokens = (new Lexer("import common\n\nFoo {\n  id: int\n}"))->tokens();
        $scope = (new ScopeParser($tokens))->parse();
        (new ImportLinker(null))->link($scope);
    }

    public function testImportUnknownPathThrows(): void
    {
        $this->expectException(\ClanCats\SchemaScript\Exception\SchemaNamespaceException::class);
        $this->evaluateWithImports("import nonexistent/path");
    }

    // -------------------------------------------------------
    // ~ Parenthesized Type Grouping
    // -------------------------------------------------------

    public function testParenthesizedArrayOfNullable(): void
    {
        $def = $this->evaluateCode("Foo {\n  data: (int?)[]\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame('data', $props[0]->getName());
        $this->assertSame(TypeKind::Array, $props[0]->getType()->getKind());
        $inner = $props[0]->getType()->getInnerType();
        $this->assertTrue($inner->isNullable());
        $this->assertSame(TypeKind::Simple, $inner->getInnerType()->getKind());
        $this->assertSame('int', $inner->getInnerType()->getName());
    }

    public function testParenthesizedNullableUnion(): void
    {
        $def = $this->evaluateCode("Foo {\n  data: (string|int)?\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame('data', $props[0]->getName());
        $this->assertTrue($props[0]->getType()->isNullable());
        $inner = $props[0]->getType()->getInnerType();
        $this->assertSame(TypeKind::Union, $inner->getKind());
        $unionTypes = $inner->getUnionTypes();
        $this->assertCount(2, $unionTypes);
        $this->assertSame('string', $unionTypes[0]->getName());
        $this->assertSame('int', $unionTypes[1]->getName());
    }

    public function testParenthesizedUnionArray(): void
    {
        $def = $this->evaluateCode("Foo {\n  tags: (string|int)[]\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame('tags', $props[0]->getName());
        $this->assertSame(TypeKind::Array, $props[0]->getType()->getKind());
        $inner = $props[0]->getType()->getInnerType();
        $this->assertSame(TypeKind::Union, $inner->getKind());
        $unionTypes = $inner->getUnionTypes();
        $this->assertCount(2, $unionTypes);
        $this->assertSame('string', $unionTypes[0]->getName());
        $this->assertSame('int', $unionTypes[1]->getName());
    }

    public function testParenthesizedNullableArrayOfNullable(): void
    {
        $def = $this->evaluateCode("Foo {\n  data: (int?)[]?\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame('data', $props[0]->getName());
        $this->assertTrue($props[0]->getType()->isNullable());
        $arrayType = $props[0]->getType()->getInnerType();
        $this->assertSame(TypeKind::Array, $arrayType->getKind());
        $elementType = $arrayType->getInnerType();
        $this->assertTrue($elementType->isNullable());
        $this->assertSame('int', $elementType->getInnerType()->getName());
    }

    public function testParenthesizedNestedArrays(): void
    {
        $def = $this->evaluateCode("Foo {\n  matrix: (int[])[]\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame('matrix', $props[0]->getName());
        $this->assertSame(TypeKind::Array, $props[0]->getType()->getKind());
        $inner = $props[0]->getType()->getInnerType();
        $this->assertSame(TypeKind::Array, $inner->getKind());
        $this->assertSame('int', $inner->getInnerType()->getName());
    }

    public function testParenthesizedIdentityPreservesType(): void
    {
        $def = $this->evaluateCode("Foo {\n  plain: (int)\n  arr: (int)[]\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame(TypeKind::Simple, $props[0]->getType()->getKind());
        $this->assertSame('int', $props[0]->getType()->getName());

        $this->assertSame(TypeKind::Array, $props[1]->getType()->getKind());
        $this->assertSame('int', $props[1]->getType()->getInnerType()->getName());
    }

    public function testParenthesizedMultiplePropertiesMixed(): void
    {
        $def = $this->evaluateCode("Foo {\n  a: (int?)[]\n  b: string|int\n  c: (bool|float)?\n}");
        $struct = $def->getStruct('Foo');
        $props = $struct->getProperties();

        $this->assertSame(TypeKind::Array, $props[0]->getType()->getKind());
        $this->assertTrue($props[0]->getType()->getInnerType()->isNullable());

        $this->assertSame(TypeKind::Union, $props[1]->getType()->getKind());
        $this->assertFalse($props[1]->getType()->isNullable());

        $this->assertTrue($props[2]->getType()->isNullable());
        $this->assertSame(TypeKind::Union, $props[2]->getType()->getInnerType()->getKind());
    }

    public function testPubTypeAliasIsPublic(): void
    {
        $def = $this->evaluateCode("[type] = {\n  pub MessageType = 'text'|'image'|'video'\n}\nMsg { type: MessageType }");
        $this->assertTrue($def->isTypeAliasPublic('MessageType'));
    }

    public function testNonPubTypeAliasIsNotPublic(): void
    {
        $def = $this->evaluateCode("[type] = {\n  MyType = 'a'|'b'\n}\nMsg { type: MyType }");
        $this->assertFalse($def->isTypeAliasPublic('MyType'));
    }

    public function testGetPublicTypeAliases(): void
    {
        $def = $this->evaluateCode("[type] = {\n  pub Visible = 'a'|'b'\n  hidden = 'c'|'d'\n}\nMsg {\n  a: Visible\n  b: hidden\n}");
        $pubAliases = $def->getPublicTypeAliases();
        $this->assertCount(1, $pubAliases);
        $this->assertArrayHasKey('Visible', $pubAliases);
    }

    public function testPubInlineObjectTypeAlias(): void
    {
        $def = $this->evaluateCode("[type] = {\n  pub position = {\n    x: int\n    y: int\n  }\n}\nBox { pos: position }");
        $this->assertTrue($def->isTypeAliasPublic('position'));
        $struct = $def->getStruct('position');
        $this->assertNotNull($struct);
        $this->assertTrue($struct->isInline());
    }

    public function testNestedNamespace(): void
    {
        $def = $this->evaluateCode("ns Visibility {\n  const public\n  ns State {\n    const active\n    const archived\n  }\n}");
        $ns = $def->getNamespaces();
        $this->assertArrayHasKey('Visibility', $ns);
        $this->assertArrayHasKey('Visibility::State', $ns);
        $this->assertSame('Visibility::public', $ns['Visibility']->getConstantValue('public'));
        $this->assertSame('Visibility::State::active', $ns['Visibility::State']->getConstantValue('active'));
        $this->assertSame('Visibility::State::archived', $ns['Visibility::State']->getConstantValue('archived'));
    }

    public function testDeeplyNestedNamespace(): void
    {
        $def = $this->evaluateCode("ns A {\n  ns B {\n    ns C {\n      const x\n    }\n  }\n}");
        $ns = $def->getNamespaces();
        $this->assertArrayHasKey('A::B::C', $ns);
        $this->assertSame('A::B::C::x', $ns['A::B::C']->getConstantValue('x'));
    }

    public function testNestedNamespaceOnlyChildrenNoParentEntry(): void
    {
        $def = $this->evaluateCode("ns A {\n  ns B {\n    const x\n  }\n}");
        $ns = $def->getNamespaces();
        $this->assertArrayNotHasKey('A', $ns);
        $this->assertArrayHasKey('A::B', $ns);
    }

    public function testNestedNamespaceReferenceResolution(): void
    {
        $def = $this->evaluateCode("ns A {\n  ns B {\n    const x = 42\n  }\n}\nns Config {\n  const ref = A::B::x\n}");
        $this->assertSame(42, $def->getNamespaces()['Config']->getConstantValue('ref'));
    }

    public function testNestedNamespaceInMetadata(): void
    {
        $def = $this->evaluateCode("ns A {\n  ns B {\n    const val\n  }\n}\nFoo {\n  [style] = A::B::val\n  x: int\n}");
        $this->assertSame('A::B::val', $def->getStruct('Foo')->getMetadataValue('style'));
    }

    public function testNestedNamespaceResolveReference(): void
    {
        $def = $this->evaluateCode("ns A {\n  ns B {\n    const x = 99\n  }\n}");
        $this->assertSame(99, $def->resolveReference('A::B', 'x'));
    }

    public function testDuplicateNestedNamespaceThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->evaluateCode("ns A {\n  ns B {\n    const x\n  }\n}\nns A {\n  ns B {\n    const y\n  }\n}");
    }

    public function testScopeLevelConstantWithNestedReference(): void
    {
        $def = $this->evaluateCode("ns A {\n  ns B {\n    const val = 7\n  }\n}\nconst myval = A::B::val\nFoo {\n  [v] = myval\n  x: int\n}");
        $this->assertSame(7, $def->getStruct('Foo')->getMetadataValue('v'));
    }

    // --- Model Annotations ---

    public function testModelAnnotation(): void
    {
        $def = $this->evaluateCode("@deprecated\nUser {\n  id: string\n}");
        $struct = $def->getStruct('User');
        $this->assertTrue($struct->hasAnnotation('deprecated'));
        $this->assertNotNull($struct->getAnnotation('deprecated'));
        $this->assertSame([], $struct->getAnnotation('deprecated')->getArguments());
    }

    public function testModelAnnotationWithArgs(): void
    {
        $def = $this->evaluateCode("@description(\"A user\")\nUser {\n  id: string\n}");
        $struct = $def->getStruct('User');
        $this->assertTrue($struct->hasAnnotation('description'));
        $this->assertSame('A user', $struct->getAnnotation('description')->getFirstArgument());
    }

    public function testModelAnnotationMultiple(): void
    {
        $def = $this->evaluateCode("@deprecated\n@internal\nUser {\n  id: string\n}");
        $struct = $def->getStruct('User');
        $this->assertTrue($struct->hasAnnotation('deprecated'));
        $this->assertTrue($struct->hasAnnotation('internal'));
    }

    public function testModelAnnotationInToArray(): void
    {
        $def = $this->evaluateCode("@deprecated\nUser {\n  id: string\n}");
        $arr = $def->getStruct('User')->toArray();
        $this->assertArrayHasKey('annotations', $arr);
        $this->assertArrayHasKey('deprecated', $arr['annotations']);
    }

    public function testModelWithoutAnnotationsHasEmptyCollection(): void
    {
        $def = $this->evaluateCode("User {\n  id: string\n}");
        $struct = $def->getStruct('User');
        $this->assertFalse($struct->hasAnnotation('deprecated'));
        $this->assertNull($struct->getAnnotation('deprecated'));
        $this->assertArrayNotHasKey('annotations', $struct->toArray());
    }

    public function testChildModelAnnotation(): void
    {
        $def = $this->evaluateCode("Parent {\n  @internal\n  Child {\n    id: string\n  }\n  name: string\n}");
        $struct = $def->getStruct('Parent/Child');
        $this->assertTrue($struct->hasAnnotation('internal'));
    }

    // --- Generics ---

    public function testGenericModelHasTypeParameters(): void
    {
        $def = $this->evaluateCode("Paginated<T> {\n  items: T[]\n  total: int\n}");
        $struct = $def->getStruct('Paginated');
        $this->assertNotNull($struct);
        $this->assertTrue($struct->isGeneric());
        $this->assertSame(['T'], $struct->getTypeParameters());
    }

    public function testGenericModelMultipleParams(): void
    {
        $def = $this->evaluateCode("Result<T, E> {\n  data: T\n  error: E\n  success: bool\n}");
        $struct = $def->getStruct('Result');
        $this->assertSame(['T', 'E'], $struct->getTypeParameters());
    }

    public function testTypeParameterResolvesToTypeParameterKind(): void
    {
        $def = $this->evaluateCode("Wrapper<T> {\n  value: T\n}");
        $struct = $def->getStruct('Wrapper');
        $valueProp = $struct->getProperties()[0];
        $this->assertSame(TypeKind::TypeParameter, $valueProp->getType()->getKind());
        $this->assertSame('T', $valueProp->getType()->getName());
    }

    public function testTypeParameterInArrayResolvesCorrectly(): void
    {
        $def = $this->evaluateCode("List<T> {\n  items: T[]\n}");
        $struct = $def->getStruct('List');
        $type = $struct->getProperties()[0]->getType();
        $this->assertSame(TypeKind::Array, $type->getKind());
        $this->assertSame(TypeKind::TypeParameter, $type->getInnerType()->getKind());
        $this->assertSame('T', $type->getInnerType()->getName());
    }

    public function testTypeParameterNullable(): void
    {
        $def = $this->evaluateCode("Maybe<T> {\n  value: T?\n}");
        $struct = $def->getStruct('Maybe');
        $type = $struct->getProperties()[0]->getType();
        $this->assertSame(TypeKind::Nullable, $type->getKind());
        $this->assertSame(TypeKind::TypeParameter, $type->getInnerType()->getKind());
    }

    public function testGenericInstantiationInProperty(): void
    {
        $def = $this->evaluateCode("Paginated<T> {\n  items: T[]\n  total: int\n}\nUser {\n  id: int\n  pages: Paginated<User>\n}");
        $userStruct = $def->getStruct('User');
        $pagesProp = $userStruct->getProperties()[1];
        $type = $pagesProp->getType();
        $this->assertSame(TypeKind::Generic, $type->getKind());
        $this->assertSame('Paginated', $type->getName());
        $this->assertCount(1, $type->getTypeArguments());
        $this->assertSame(TypeKind::Reference, $type->getTypeArguments()[0]->getKind());
        $this->assertSame('User', $type->getTypeArguments()[0]->getName());
    }

    public function testMapTypeFromStdlib(): void
    {
        $def = $this->evaluateCode("Config {\n  settings: map<string, string>\n}");
        $struct = $def->getStruct('Config');
        $type = $struct->getProperties()[0]->getType();
        $this->assertSame(TypeKind::Generic, $type->getKind());
        $this->assertSame('map', $type->getName());
        $this->assertCount(2, $type->getTypeArguments());
        $this->assertSame('string', $type->getTypeArguments()[0]->getName());
        $this->assertSame('string', $type->getTypeArguments()[1]->getName());
    }

    public function testMapTypeNullable(): void
    {
        $def = $this->evaluateCode("Config {\n  settings: map<string, int>?\n}");
        $type = $def->getStruct('Config')->getProperties()[0]->getType();
        $this->assertSame(TypeKind::Nullable, $type->getKind());
        $inner = $type->getInnerType();
        $this->assertSame(TypeKind::Generic, $inner->getKind());
        $this->assertSame('map', $inner->getName());
    }

    public function testMapTypeArray(): void
    {
        $def = $this->evaluateCode("Config {\n  items: map<string, int>[]\n}");
        $type = $def->getStruct('Config')->getProperties()[0]->getType();
        $this->assertSame(TypeKind::Array, $type->getKind());
        $inner = $type->getInnerType();
        $this->assertSame(TypeKind::Generic, $inner->getKind());
        $this->assertSame('map', $inner->getName());
    }

    public function testNestedMapType(): void
    {
        $def = $this->evaluateCode("Config {\n  data: map<string, map<string, int>>\n}");
        $type = $def->getStruct('Config')->getProperties()[0]->getType();
        $this->assertSame(TypeKind::Generic, $type->getKind());
        $valueArg = $type->getTypeArguments()[1];
        $this->assertSame(TypeKind::Generic, $valueArg->getKind());
        $this->assertSame('map', $valueArg->getName());
    }

    public function testNonGenericModelHasNoTypeParameters(): void
    {
        $def = $this->evaluateCode("User {\n  id: int\n}");
        $struct = $def->getStruct('User');
        $this->assertFalse($struct->isGeneric());
        $this->assertSame([], $struct->getTypeParameters());
    }

    public function testMapStructFromStdlibIsGeneric(): void
    {
        $def = $this->evaluateCode("User {\n  id: int\n}");
        $mapStruct = $def->getStruct('map');
        $this->assertNotNull($mapStruct);
        $this->assertTrue($mapStruct->isGeneric());
        $this->assertSame(['K', 'V'], $mapStruct->getTypeParameters());
    }

    public function testDuplicateTypeParameterThrows(): void
    {
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('Duplicate type parameter');
        $this->evaluateCode("Wrapper<T, T> {\n  a: T\n  b: T\n}");
    }

    public function testGenericArityMismatchCaughtByValidator(): void
    {
        $def = $this->evaluateCode("Paginated<T> {\n  items: T[]\n}\nUser {\n  pages: Paginated<int, string>\n}");
        $validator = new \ClanCats\SchemaScript\Schema\DefinitionValidator();
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('passes 2 type argument(s)');
        $validator->validate($def);
    }

    public function testGenericArgsOnNonGenericStructCaughtByValidator(): void
    {
        $def = $this->evaluateCode("User {\n  id: int\n}\nPost {\n  author: User<string>\n}");
        $validator = new \ClanCats\SchemaScript\Schema\DefinitionValidator();
        $this->expectException(EvaluatorException::class);
        $this->expectExceptionMessage('non-generic struct');
        $validator->validate($def);
    }
}
