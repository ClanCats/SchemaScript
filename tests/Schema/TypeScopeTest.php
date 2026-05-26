<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\TypeScope;
use ClanCats\SchemaScript\Schema\TypeScopeEntry;

class TypeScopeTest extends TestCase
{
    public function testEmptyScope(): void
    {
        $scope = new TypeScope();
        $this->assertNull($scope->resolveType('foo'));
        $this->assertFalse($scope->isModelName('foo'));
        $this->assertFalse($scope->hasName('foo'));
        $this->assertSame([], $scope->getLocalTypeAliases());
        $this->assertSame([], $scope->getLocalModelNames());
    }

    public function testRegisterAndResolveTypeAlias(): void
    {
        $scope = new TypeScope();
        $scope->registerType('UUID', false);
        $entry = $scope->resolveType('UUID');
        $this->assertInstanceOf(TypeScopeEntry::class, $entry);
        $this->assertSame('UUID', $entry->getName());
        $this->assertFalse($entry->hasTypeDefinition());
        $this->assertNull($scope->resolveType('other'));
    }

    public function testRegisterAndCheckModelName(): void
    {
        $scope = new TypeScope();
        $scope->registerModelName('User');
        $this->assertTrue($scope->isModelName('User'));
        $this->assertFalse($scope->isModelName('Post'));
    }

    public function testHasNameWithAlias(): void
    {
        $scope = new TypeScope();
        $scope->registerType('UUID', false);
        $this->assertTrue($scope->hasName('UUID'));
        $this->assertFalse($scope->hasName('other'));
    }

    public function testHasNameWithModel(): void
    {
        $scope = new TypeScope();
        $scope->registerModelName('User');
        $this->assertTrue($scope->hasName('User'));
    }

    public function testParentScopeResolvesTypeAlias(): void
    {
        $parent = new TypeScope();
        $parent->registerType('UUID', false);

        $child = new TypeScope($parent);
        $entry = $child->resolveType('UUID');
        $this->assertNotNull($entry);
        $this->assertSame('UUID', $entry->getName());
    }

    public function testParentScopeResolvesModelName(): void
    {
        $parent = new TypeScope();
        $parent->registerModelName('User');

        $child = new TypeScope($parent);
        $this->assertTrue($child->isModelName('User'));
    }

    public function testChildOverridesParentAlias(): void
    {
        $parent = new TypeScope();
        $parent->registerType('ID', false);

        $child = new TypeScope($parent);
        $child->registerType('ID', true);

        $childEntry = $child->resolveType('ID');
        $parentEntry = $parent->resolveType('ID');

        $this->assertTrue($childEntry->hasTypeDefinition());
        $this->assertFalse($parentEntry->hasTypeDefinition());
    }

    public function testParentHasNameFallback(): void
    {
        $parent = new TypeScope();
        $parent->registerType('UUID', false);
        $parent->registerModelName('Base');

        $child = new TypeScope($parent);
        $this->assertTrue($child->hasName('UUID'));
        $this->assertTrue($child->hasName('Base'));
        $this->assertFalse($child->hasName('Missing'));
    }

    public function testGetLocalTypeAliases(): void
    {
        $parent = new TypeScope();
        $parent->registerType('ParentAlias', false);

        $child = new TypeScope($parent);
        $child->registerType('ChildAlias', true);

        $local = $child->getLocalTypeAliases();
        $this->assertCount(1, $local);
        $this->assertArrayHasKey('ChildAlias', $local);
        $this->assertArrayNotHasKey('ParentAlias', $local);
    }

    public function testGetLocalModelNames(): void
    {
        $parent = new TypeScope();
        $parent->registerModelName('ParentModel');

        $child = new TypeScope($parent);
        $child->registerModelName('ChildModel');

        $local = $child->getLocalModelNames();
        $this->assertCount(1, $local);
        $this->assertArrayHasKey('ChildModel', $local);
        $this->assertArrayNotHasKey('ParentModel', $local);
    }

    public function testNoParentReturnsNullForMissing(): void
    {
        $scope = new TypeScope();
        $this->assertNull($scope->resolveType('missing'));
        $this->assertFalse($scope->isModelName('missing'));
    }
}
