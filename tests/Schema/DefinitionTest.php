<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\TypeAlias;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\MetadataEntry;
use ClanCats\SchemaScript\Schema\AnnotationCollection;

class DefinitionTest extends TestCase
{
    public function testEmptyDefinition(): void
    {
        $d = new Definition();
        $this->assertSame([], $d->getMetadata());
        $this->assertSame([], $d->getTypeAliases());
        $this->assertSame([], $d->getNamespaces());
        $this->assertSame([], $d->getStructs());
    }

    public function testGetMetadata(): void
    {
        $entries = [
            new MetadataEntry('version', '1.0', new AnnotationCollection()),
        ];
        $d = new Definition($entries);
        $this->assertCount(1, $d->getMetadata());
        $this->assertSame('version', $d->getMetadata()[0]->getKey());
    }

    public function testFindMetadataValue(): void
    {
        $entries = [
            new MetadataEntry('version', '1.0', new AnnotationCollection()),
            new MetadataEntry('name', 'Test', new AnnotationCollection()),
        ];
        $d = new Definition($entries);
        $this->assertSame('1.0', $d->findMetadataValue('version'));
        $this->assertSame('Test', $d->findMetadataValue('name'));
    }

    public function testFindMetadataValueReturnsNullForMissing(): void
    {
        $d = new Definition();
        $this->assertNull($d->findMetadataValue('nonexistent'));
    }

    public function testGetTypeAlias(): void
    {
        $alias = new TypeAlias('UUID', false, Type::simple('string'));
        $d = new Definition([], ['UUID' => $alias]);
        $this->assertSame($alias, $d->getTypeAlias('UUID'));
        $this->assertNull($d->getTypeAlias('nonexistent'));
    }

    public function testGetTypeAliases(): void
    {
        $aliases = [
            'UUID' => new TypeAlias('UUID', false, Type::simple('string')),
            'ID' => new TypeAlias('ID', true, Type::simple('int')),
        ];
        $d = new Definition([], $aliases);
        $this->assertCount(2, $d->getTypeAliases());
    }

    public function testIsTypeAliasPublic(): void
    {
        $aliases = [
            'UUID' => new TypeAlias('UUID', true, Type::simple('string')),
            'ID' => new TypeAlias('ID', false, Type::simple('int')),
        ];
        $d = new Definition([], $aliases);
        $this->assertTrue($d->isTypeAliasPublic('UUID'));
        $this->assertFalse($d->isTypeAliasPublic('ID'));
        $this->assertFalse($d->isTypeAliasPublic('nonexistent'));
    }

    public function testGetPublicTypeAliases(): void
    {
        $aliases = [
            'UUID' => new TypeAlias('UUID', true, Type::simple('string')),
            'ID' => new TypeAlias('ID', false, Type::simple('int')),
            'Timestamp' => new TypeAlias('Timestamp', true, Type::simple('int')),
        ];
        $d = new Definition([], $aliases);
        $public = $d->getPublicTypeAliases();
        $this->assertCount(2, $public);
        $this->assertArrayHasKey('UUID', $public);
        $this->assertArrayHasKey('Timestamp', $public);
        $this->assertArrayNotHasKey('ID', $public);
    }

    public function testGetTypeAliasResolvedType(): void
    {
        $type = Type::simple('string');
        $aliases = [
            'UUID' => new TypeAlias('UUID', false, $type),
            'NoType' => new TypeAlias('NoType', false, null),
        ];
        $d = new Definition([], $aliases);
        $this->assertSame($type, $d->getTypeAliasResolvedType('UUID'));
        $this->assertNull($d->getTypeAliasResolvedType('NoType'));
        $this->assertNull($d->getTypeAliasResolvedType('nonexistent'));
    }

    public function testGetNamespaceConstants(): void
    {
        $namespaces = [
            'Config' => ['MAX_SIZE' => 100, 'NAME' => 'test'],
        ];
        $d = new Definition([], [], $namespaces);
        $this->assertSame(['MAX_SIZE' => 100, 'NAME' => 'test'], $d->getNamespaceConstants('Config'));
        $this->assertNull($d->getNamespaceConstants('nonexistent'));
    }

    public function testGetNamespaces(): void
    {
        $namespaces = [
            'Config' => ['A' => 1],
            'Env' => ['B' => 2],
        ];
        $d = new Definition([], [], $namespaces);
        $this->assertCount(2, $d->getNamespaces());
    }

    public function testResolveReference(): void
    {
        $namespaces = [
            'Config' => ['MAX_SIZE' => 100, 'NAME' => 'test'],
        ];
        $d = new Definition([], [], $namespaces);
        $this->assertSame(100, $d->resolveReference('Config', 'MAX_SIZE'));
        $this->assertSame('test', $d->resolveReference('Config', 'NAME'));
    }

    public function testResolveReferenceReturnsNullForMissingNamespace(): void
    {
        $d = new Definition();
        $this->assertNull($d->resolveReference('Missing', 'KEY'));
    }

    public function testResolveReferenceReturnsNullForMissingConstant(): void
    {
        $namespaces = ['Config' => ['A' => 1]];
        $d = new Definition([], [], $namespaces);
        $this->assertNull($d->resolveReference('Config', 'MISSING'));
    }

    public function testGetStruct(): void
    {
        $struct = new Struct('User', false);
        $d = new Definition([], [], [], ['User' => $struct]);
        $this->assertSame($struct, $d->getStruct('User'));
        $this->assertNull($d->getStruct('nonexistent'));
    }

    public function testGetModels(): void
    {
        $structs = [
            'User' => new Struct('User', false),
            'UserAddress' => new Struct('UserAddress', true),
            'Post' => new Struct('Post', false),
        ];
        $d = new Definition([], [], [], $structs);
        $models = $d->getModels();
        $this->assertCount(2, $models);
        $this->assertArrayHasKey('User', $models);
        $this->assertArrayHasKey('Post', $models);
        $this->assertArrayNotHasKey('UserAddress', $models);
    }

    public function testGetInlineStructs(): void
    {
        $structs = [
            'User' => new Struct('User', false),
            'UserAddress' => new Struct('UserAddress', true),
            'PostMeta' => new Struct('PostMeta', true),
        ];
        $d = new Definition([], [], [], $structs);
        $inline = $d->getInlineStructs();
        $this->assertCount(2, $inline);
        $this->assertArrayHasKey('UserAddress', $inline);
        $this->assertArrayHasKey('PostMeta', $inline);
        $this->assertArrayNotHasKey('User', $inline);
    }

    public function testToArrayEmpty(): void
    {
        $d = new Definition();
        $this->assertSame([], $d->toArray());
    }

    public function testToArrayWithMetadata(): void
    {
        $entries = [new MetadataEntry('version', '1.0', new AnnotationCollection())];
        $d = new Definition($entries);
        $result = $d->toArray();
        $this->assertArrayHasKey('metadata', $result);
        $this->assertCount(1, $result['metadata']);
    }

    public function testToArrayWithTypeAliases(): void
    {
        $aliases = ['UUID' => new TypeAlias('UUID', false, Type::simple('string'))];
        $d = new Definition([], $aliases);
        $result = $d->toArray();
        $this->assertArrayHasKey('typeAliases', $result);
    }

    public function testToArrayWithNamespaces(): void
    {
        $d = new Definition([], [], ['Config' => ['A' => 1]]);
        $result = $d->toArray();
        $this->assertArrayHasKey('namespaces', $result);
    }

    public function testToArrayWithStructs(): void
    {
        $structs = ['User' => new Struct('User', false)];
        $d = new Definition([], [], [], $structs);
        $result = $d->toArray();
        $this->assertArrayHasKey('structs', $result);
    }
}
