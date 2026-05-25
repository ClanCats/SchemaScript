<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\MetadataEntry;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\AnnotationCollection;

class StructTest extends TestCase
{
    public function testConstructMinimal(): void
    {
        $s = new Struct('User', false);
        $this->assertSame('User', $s->getName());
        $this->assertFalse($s->isInline());
        $this->assertSame([], $s->getProperties());
        $this->assertSame([], $s->getMetadata());
    }

    public function testConstructInline(): void
    {
        $s = new Struct('UserAddress', true);
        $this->assertTrue($s->isInline());
    }

    public function testConstructWithProperties(): void
    {
        $props = [
            new StructProperty('name', Type::simple('string'), false),
            new StructProperty('age', Type::simple('int'), true),
        ];
        $s = new Struct('User', false, $props);
        $this->assertCount(2, $s->getProperties());
        $this->assertSame('name', $s->getProperties()[0]->getName());
    }

    public function testGetMetadataValue(): void
    {
        $metadata = [
            new MetadataEntry('table', 'users', new AnnotationCollection()),
            new MetadataEntry('engine', 'InnoDB', new AnnotationCollection()),
        ];
        $s = new Struct('User', false, [], $metadata);
        $this->assertSame('users', $s->getMetadataValue('table'));
        $this->assertSame('InnoDB', $s->getMetadataValue('engine'));
    }

    public function testGetMetadataValueReturnsNullForMissing(): void
    {
        $s = new Struct('User', false);
        $this->assertNull($s->getMetadataValue('nonexistent'));
    }

    public function testGetMetadataValueWithEntries(): void
    {
        $metadata = [
            new MetadataEntry('key', 'value', new AnnotationCollection()),
        ];
        $s = new Struct('Test', false, [], $metadata);
        $this->assertNull($s->getMetadataValue('missing'));
    }

    public function testToArrayMinimal(): void
    {
        $s = new Struct('User', false);
        $this->assertSame([
            'name' => 'User',
            'properties' => [],
        ], $s->toArray());
    }

    public function testToArrayInline(): void
    {
        $s = new Struct('Address', true);
        $result = $s->toArray();
        $this->assertTrue($result['inline']);
    }

    public function testToArrayNotInlineOmitsKey(): void
    {
        $s = new Struct('User', false);
        $this->assertArrayNotHasKey('inline', $s->toArray());
    }

    public function testToArrayWithProperties(): void
    {
        $props = [new StructProperty('name', Type::simple('string'), false)];
        $s = new Struct('User', false, $props);
        $result = $s->toArray();
        $this->assertCount(1, $result['properties']);
        $this->assertSame('name', $result['properties'][0]['name']);
    }

    public function testToArrayWithMetadata(): void
    {
        $metadata = [new MetadataEntry('table', 'users', new AnnotationCollection())];
        $s = new Struct('User', false, [], $metadata);
        $result = $s->toArray();
        $this->assertArrayHasKey('metadata', $result);
        $this->assertCount(1, $result['metadata']);
    }

    public function testToArrayOmitsMetadataWhenEmpty(): void
    {
        $s = new Struct('User', false);
        $this->assertArrayNotHasKey('metadata', $s->toArray());
    }
}
