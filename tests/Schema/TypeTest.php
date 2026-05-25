<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\Type;
use ClanCats\SchemaScript\Schema\TypeKind;
use ClanCats\SchemaScript\Schema\TypeVisitorInterface;

class TypeTest extends TestCase
{
    public function testSimple(): void
    {
        $t = Type::simple('string');
        $this->assertSame(TypeKind::Simple, $t->getKind());
        $this->assertSame('string', $t->getName());
        $this->assertNull($t->getInnerType());
        $this->assertSame([], $t->getUnionTypes());
    }

    public function testReference(): void
    {
        $t = Type::reference('User');
        $this->assertSame(TypeKind::Reference, $t->getKind());
        $this->assertSame('User', $t->getName());
    }

    public function testAlias(): void
    {
        $t = Type::alias('UUID');
        $this->assertSame(TypeKind::Alias, $t->getKind());
        $this->assertSame('UUID', $t->getName());
    }

    public function testArray(): void
    {
        $inner = Type::simple('int');
        $t = Type::array($inner);
        $this->assertSame(TypeKind::Array, $t->getKind());
        $this->assertNull($t->getName());
        $this->assertSame($inner, $t->getInnerType());
    }

    public function testNullable(): void
    {
        $inner = Type::simple('string');
        $t = Type::nullable($inner);
        $this->assertSame(TypeKind::Nullable, $t->getKind());
        $this->assertSame($inner, $t->getInnerType());
    }

    public function testUnion(): void
    {
        $a = Type::simple('string');
        $b = Type::simple('int');
        $t = Type::union([$a, $b]);
        $this->assertSame(TypeKind::Union, $t->getKind());
        $this->assertSame([$a, $b], $t->getUnionTypes());
        $this->assertNull($t->getInnerType());
    }

    public function testStringLiteral(): void
    {
        $t = Type::stringLiteral('hello');
        $this->assertSame(TypeKind::StringLiteral, $t->getKind());
        $this->assertSame('hello', $t->getName());
    }

    public function testIsSimple(): void
    {
        $this->assertTrue(Type::simple('int')->isSimple());
        $this->assertFalse(Type::reference('User')->isSimple());
    }

    public function testIsReference(): void
    {
        $this->assertTrue(Type::reference('User')->isReference());
        $this->assertFalse(Type::simple('int')->isReference());
    }

    public function testIsAlias(): void
    {
        $this->assertTrue(Type::alias('UUID')->isAlias());
        $this->assertFalse(Type::simple('int')->isAlias());
    }

    public function testIsArray(): void
    {
        $this->assertTrue(Type::array(Type::simple('int'))->isArray());
        $this->assertFalse(Type::simple('int')->isArray());
    }

    public function testIsNullable(): void
    {
        $this->assertTrue(Type::nullable(Type::simple('int'))->isNullable());
        $this->assertFalse(Type::simple('int')->isNullable());
    }

    public function testIsUnion(): void
    {
        $this->assertTrue(Type::union([Type::simple('int'), Type::simple('string')])->isUnion());
        $this->assertFalse(Type::simple('int')->isUnion());
    }

    public function testIsStringLiteral(): void
    {
        $this->assertTrue(Type::stringLiteral('x')->isStringLiteral());
        $this->assertFalse(Type::simple('int')->isStringLiteral());
    }

    public function testAcceptSimple(): void
    {
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitSimple')->with('int')->willReturn('result');
        $this->assertSame('result', Type::simple('int')->accept($visitor));
    }

    public function testAcceptReference(): void
    {
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitReference')->with('User')->willReturn('ref');
        $this->assertSame('ref', Type::reference('User')->accept($visitor));
    }

    public function testAcceptAlias(): void
    {
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitAlias')->with('UUID')->willReturn('alias');
        $this->assertSame('alias', Type::alias('UUID')->accept($visitor));
    }

    public function testAcceptArray(): void
    {
        $inner = Type::simple('int');
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitArray')->with($inner)->willReturn('arr');
        $this->assertSame('arr', Type::array($inner)->accept($visitor));
    }

    public function testAcceptNullable(): void
    {
        $inner = Type::simple('string');
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitNullable')->with($inner)->willReturn('null');
        $this->assertSame('null', Type::nullable($inner)->accept($visitor));
    }

    public function testAcceptUnion(): void
    {
        $types = [Type::simple('int'), Type::simple('string')];
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitUnion')->with($types)->willReturn('union');
        $this->assertSame('union', Type::union($types)->accept($visitor));
    }

    public function testAcceptStringLiteral(): void
    {
        $visitor = $this->createMock(TypeVisitorInterface::class);
        $visitor->expects($this->once())->method('visitStringLiteral')->with('hello')->willReturn('lit');
        $this->assertSame('lit', Type::stringLiteral('hello')->accept($visitor));
    }

    public function testToArraySimple(): void
    {
        $this->assertSame(
            ['kind' => 'simple', 'name' => 'int'],
            Type::simple('int')->toArray()
        );
    }

    public function testToArrayReference(): void
    {
        $this->assertSame(
            ['kind' => 'reference', 'name' => 'User'],
            Type::reference('User')->toArray()
        );
    }

    public function testToArrayAlias(): void
    {
        $this->assertSame(
            ['kind' => 'alias', 'name' => 'UUID'],
            Type::alias('UUID')->toArray()
        );
    }

    public function testToArrayArray(): void
    {
        $this->assertSame(
            ['kind' => 'array', 'innerType' => ['kind' => 'simple', 'name' => 'int']],
            Type::array(Type::simple('int'))->toArray()
        );
    }

    public function testToArrayNullable(): void
    {
        $this->assertSame(
            ['kind' => 'nullable', 'innerType' => ['kind' => 'simple', 'name' => 'string']],
            Type::nullable(Type::simple('string'))->toArray()
        );
    }

    public function testToArrayUnion(): void
    {
        $this->assertSame(
            ['kind' => 'union', 'types' => [
                ['kind' => 'simple', 'name' => 'int'],
                ['kind' => 'simple', 'name' => 'string'],
            ]],
            Type::union([Type::simple('int'), Type::simple('string')])->toArray()
        );
    }

    public function testToArrayStringLiteral(): void
    {
        $this->assertSame(
            ['kind' => 'string_literal', 'name' => 'hello'],
            Type::stringLiteral('hello')->toArray()
        );
    }
}
