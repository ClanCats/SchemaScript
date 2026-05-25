<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;

class AnnotationCollectionTest extends TestCase
{
    private function makeCollection(array $items = []): AnnotationCollection
    {
        $annotations = [];
        foreach ($items as $name => $args) {
            $annotations[$name] = new Annotation($name, $args);
        }
        return new AnnotationCollection($annotations);
    }

    public function testEmptyCollection(): void
    {
        $c = new AnnotationCollection();
        $this->assertTrue($c->isEmpty());
        $this->assertSame([], $c->all());
        $this->assertSame([], $c->toArray());
    }

    public function testIsEmptyReturnsFalseWhenPopulated(): void
    {
        $c = $this->makeCollection(['deprecated' => []]);
        $this->assertFalse($c->isEmpty());
    }

    public function testHas(): void
    {
        $c = $this->makeCollection(['local' => ['avatarImageId']]);
        $this->assertTrue($c->has('local'));
        $this->assertFalse($c->has('missing'));
    }

    public function testGet(): void
    {
        $c = $this->makeCollection(['enum' => ['text', 'image']]);
        $annotation = $c->get('enum');
        $this->assertInstanceOf(Annotation::class, $annotation);
        $this->assertSame('enum', $annotation->getName());
        $this->assertSame(['text', 'image'], $annotation->getArguments());
    }

    public function testGetReturnsNullForMissing(): void
    {
        $c = $this->makeCollection(['deprecated' => []]);
        $this->assertNull($c->get('missing'));
    }

    public function testAll(): void
    {
        $c = $this->makeCollection([
            'first' => [],
            'second' => ['a', 'b'],
        ]);
        $all = $c->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('first', $all);
        $this->assertArrayHasKey('second', $all);
        $this->assertInstanceOf(Annotation::class, $all['first']);
        $this->assertInstanceOf(Annotation::class, $all['second']);
    }

    public function testGetLangType(): void
    {
        $c = $this->makeCollection([
            'lang.php' => ['int'],
            'lang.ts' => ['bigint'],
        ]);
        $this->assertSame('int', $c->getLangType('php'));
        $this->assertSame('bigint', $c->getLangType('ts'));
    }

    public function testGetLangTypeReturnsNullWhenMissing(): void
    {
        $c = $this->makeCollection(['deprecated' => []]);
        $this->assertNull($c->getLangType('php'));
    }

    public function testGetLangTypeReturnsNullWhenNoArguments(): void
    {
        $c = $this->makeCollection(['lang.php' => []]);
        $this->assertNull($c->getLangType('php'));
    }

    public function testToArray(): void
    {
        $c = $this->makeCollection([
            'local' => ['avatarImageId'],
            'deprecated' => [],
        ]);
        $this->assertSame([
            'local' => ['avatarImageId'],
            'deprecated' => [],
        ], $c->toArray());
    }

    public function testMultipleAnnotations(): void
    {
        $c = $this->makeCollection([
            'lang.php' => ['string'],
            'lang.ts' => ['string'],
            'deprecated' => [],
            'enum' => ['a', 'b', 'c'],
        ]);
        $this->assertCount(4, $c->all());
        $this->assertTrue($c->has('deprecated'));
        $this->assertTrue($c->has('enum'));
        $this->assertSame(['a', 'b', 'c'], $c->get('enum')->getArguments());
        $this->assertSame('string', $c->getLangType('php'));
    }
}
