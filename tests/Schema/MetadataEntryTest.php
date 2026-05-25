<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\MetadataEntry;
use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;

class MetadataEntryTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $attrs = new AnnotationCollection();
        $e = new MetadataEntry('version', '1.0', $attrs);
        $this->assertSame('version', $e->getKey());
        $this->assertSame('1.0', $e->getValue());
        $this->assertSame($attrs, $e->getAttributes());
    }

    public function testStringValue(): void
    {
        $e = new MetadataEntry('name', 'MySchema', new AnnotationCollection());
        $this->assertSame('MySchema', $e->getValue());
    }

    public function testIntValue(): void
    {
        $e = new MetadataEntry('version', 42, new AnnotationCollection());
        $this->assertSame(42, $e->getValue());
    }

    public function testBoolValue(): void
    {
        $e = new MetadataEntry('enabled', true, new AnnotationCollection());
        $this->assertTrue($e->getValue());
    }

    public function testNullValue(): void
    {
        $e = new MetadataEntry('optional', null, new AnnotationCollection());
        $this->assertNull($e->getValue());
    }

    public function testArrayValue(): void
    {
        $e = new MetadataEntry('tags', ['a', 'b'], new AnnotationCollection());
        $this->assertSame(['a', 'b'], $e->getValue());
    }

    public function testWithAttributes(): void
    {
        $attrs = new AnnotationCollection(['deprecated' => new Annotation('deprecated')]);
        $e = new MetadataEntry('key', 'val', $attrs);
        $this->assertFalse($e->getAttributes()->isEmpty());
        $this->assertTrue($e->getAttributes()->has('deprecated'));
    }

    public function testToArray(): void
    {
        $attrs = new AnnotationCollection(['tag' => new Annotation('tag', ['v1'])]);
        $e = new MetadataEntry('version', '1.0', $attrs);
        $result = $e->toArray();
        $this->assertSame('version', $result['key']);
        $this->assertSame('1.0', $result['value']);
        $this->assertIsArray($result['attributes']);
    }

    public function testToArrayEmptyAttributes(): void
    {
        $e = new MetadataEntry('key', 'val', new AnnotationCollection());
        $result = $e->toArray();
        $this->assertSame([], $result['attributes']);
    }
}
