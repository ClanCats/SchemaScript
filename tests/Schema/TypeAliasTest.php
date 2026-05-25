<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\TypeAlias;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;

class TypeAliasTest extends TestCase
{
    public function testConstructMinimal(): void
    {
        $a = new TypeAlias('ID', false, null);
        $this->assertSame('ID', $a->getName());
        $this->assertFalse($a->isPublic());
        $this->assertNull($a->getResolvedType());
        $this->assertTrue($a->getAnnotations()->isEmpty());
    }

    public function testConstructPublicWithResolvedType(): void
    {
        $type = Type::simple('string');
        $a = new TypeAlias('UUID', true, $type);
        $this->assertSame('UUID', $a->getName());
        $this->assertTrue($a->isPublic());
        $this->assertSame($type, $a->getResolvedType());
    }

    public function testConstructWithAnnotations(): void
    {
        $annotations = new AnnotationCollection([
            'lang.php' => new Annotation('lang.php', ['string']),
        ]);
        $a = new TypeAlias('UUID', false, Type::simple('string'), $annotations);
        $this->assertFalse($a->getAnnotations()->isEmpty());
        $this->assertTrue($a->getAnnotations()->has('lang.php'));
    }

    public function testGetLangType(): void
    {
        $annotations = new AnnotationCollection([
            'lang.php' => new Annotation('lang.php', ['string']),
            'lang.ts' => new Annotation('lang.ts', ['string']),
        ]);
        $a = new TypeAlias('UUID', false, Type::simple('string'), $annotations);
        $this->assertSame('string', $a->getLangType('php'));
        $this->assertSame('string', $a->getLangType('ts'));
        $this->assertNull($a->getLangType('go'));
    }

    public function testToArrayMinimal(): void
    {
        $a = new TypeAlias('ID', false, null);
        $this->assertSame(['name' => 'ID'], $a->toArray());
    }

    public function testToArrayPublic(): void
    {
        $a = new TypeAlias('ID', true, null);
        $result = $a->toArray();
        $this->assertSame('ID', $result['name']);
        $this->assertTrue($result['public']);
    }

    public function testToArrayWithResolvedType(): void
    {
        $a = new TypeAlias('UUID', false, Type::simple('string'));
        $result = $a->toArray();
        $this->assertSame(['kind' => 'simple', 'name' => 'string'], $result['resolvedType']);
        $this->assertArrayNotHasKey('public', $result);
    }

    public function testToArrayWithAnnotations(): void
    {
        $annotations = new AnnotationCollection([
            'lang.php' => new Annotation('lang.php', ['string']),
        ]);
        $a = new TypeAlias('UUID', false, null, $annotations);
        $result = $a->toArray();
        $this->assertArrayHasKey('annotations', $result);
    }

    public function testToArrayFull(): void
    {
        $annotations = new AnnotationCollection([
            'deprecated' => new Annotation('deprecated'),
        ]);
        $a = new TypeAlias('OldID', true, Type::simple('int'), $annotations);
        $result = $a->toArray();
        $this->assertSame('OldID', $result['name']);
        $this->assertTrue($result['public']);
        $this->assertArrayHasKey('resolvedType', $result);
        $this->assertArrayHasKey('annotations', $result);
    }
}
