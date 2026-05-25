<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\Annotation;

class AnnotationTest extends TestCase
{
    public function testConstructWithNameOnly(): void
    {
        $a = new Annotation('deprecated');
        $this->assertSame('deprecated', $a->getName());
        $this->assertSame([], $a->getArguments());
    }

    public function testConstructWithArguments(): void
    {
        $a = new Annotation('lang.php', ['int']);
        $this->assertSame('lang.php', $a->getName());
        $this->assertSame(['int'], $a->getArguments());
    }

    public function testGetFirstArgument(): void
    {
        $a = new Annotation('enum', ['text', 'image']);
        $this->assertSame('text', $a->getFirstArgument());
    }

    public function testGetFirstArgumentReturnsNullWhenEmpty(): void
    {
        $a = new Annotation('deprecated');
        $this->assertNull($a->getFirstArgument());
    }

    public function testToArray(): void
    {
        $a = new Annotation('local', ['avatarImageId']);
        $this->assertSame([
            'name' => 'local',
            'arguments' => ['avatarImageId'],
        ], $a->toArray());
    }

    public function testToArrayWithNoArguments(): void
    {
        $a = new Annotation('deprecated');
        $this->assertSame([
            'name' => 'deprecated',
            'arguments' => [],
        ], $a->toArray());
    }

    public function testMixedArgumentTypes(): void
    {
        $a = new Annotation('meta', ['text', 42, 3.14, true]);
        $this->assertSame(['text', 42, 3.14, true], $a->getArguments());
        $this->assertSame('text', $a->getFirstArgument());
    }
}
