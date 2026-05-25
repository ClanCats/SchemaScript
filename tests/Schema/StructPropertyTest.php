<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;

class StructPropertyTest extends TestCase
{
    public function testConstructMinimal(): void
    {
        $type = Type::simple('string');
        $p = new StructProperty('name', $type, false);
        $this->assertSame('name', $p->getName());
        $this->assertSame($type, $p->getType());
        $this->assertFalse($p->isOptional());
        $this->assertTrue($p->getAnnotations()->isEmpty());
        $this->assertNull($p->getComment());
    }

    public function testConstructOptionalWithComment(): void
    {
        $type = Type::nullable(Type::simple('int'));
        $p = new StructProperty('age', $type, true, new AnnotationCollection(), 'The user age');
        $this->assertTrue($p->isOptional());
        $this->assertSame('The user age', $p->getComment());
    }

    public function testHasAnnotation(): void
    {
        $annotations = new AnnotationCollection([
            'deprecated' => new Annotation('deprecated'),
            'lang.php' => new Annotation('lang.php', ['int']),
        ]);
        $p = new StructProperty('id', Type::simple('int'), false, $annotations);
        $this->assertTrue($p->hasAnnotation('deprecated'));
        $this->assertTrue($p->hasAnnotation('lang.php'));
        $this->assertFalse($p->hasAnnotation('nonexistent'));
    }

    public function testGetAnnotation(): void
    {
        $ann = new Annotation('lang.php', ['int']);
        $annotations = new AnnotationCollection(['lang.php' => $ann]);
        $p = new StructProperty('id', Type::simple('int'), false, $annotations);
        $this->assertSame($ann, $p->getAnnotation('lang.php'));
        $this->assertNull($p->getAnnotation('nonexistent'));
    }

    public function testToArrayMinimal(): void
    {
        $p = new StructProperty('title', Type::simple('string'), false);
        $this->assertSame([
            'name' => 'title',
            'type' => ['kind' => 'simple', 'name' => 'string'],
        ], $p->toArray());
    }

    public function testToArrayOptional(): void
    {
        $p = new StructProperty('bio', Type::simple('string'), true);
        $result = $p->toArray();
        $this->assertTrue($result['optional']);
    }

    public function testToArrayWithAnnotations(): void
    {
        $annotations = new AnnotationCollection(['deprecated' => new Annotation('deprecated')]);
        $p = new StructProperty('old', Type::simple('string'), false, $annotations);
        $result = $p->toArray();
        $this->assertArrayHasKey('annotations', $result);
    }

    public function testToArrayWithComment(): void
    {
        $p = new StructProperty('name', Type::simple('string'), false, new AnnotationCollection(), 'Full name');
        $result = $p->toArray();
        $this->assertSame('Full name', $result['comment']);
    }

    public function testToArrayFull(): void
    {
        $annotations = new AnnotationCollection(['unique' => new Annotation('unique')]);
        $p = new StructProperty('email', Type::simple('string'), true, $annotations, 'User email');
        $result = $p->toArray();
        $this->assertSame('email', $result['name']);
        $this->assertTrue($result['optional']);
        $this->assertArrayHasKey('annotations', $result);
        $this->assertSame('User email', $result['comment']);
    }
}
