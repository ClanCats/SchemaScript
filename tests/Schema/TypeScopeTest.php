<?php

namespace ClanCats\SchemaScript\Tests\Schema;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\Schema\TypeScope;
use ClanCats\SchemaScript\Node\TypeAliasNode;

class TypeScopeTest extends TestCase
{
    private function makeTypeAliasNode(string $name): TypeAliasNode
    {
        return new TypeAliasNode($name);
    }

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
        $alias = $this->makeTypeAliasNode('UUID');
        $scope->registerTypeAlias($alias);
        $this->assertSame($alias, $scope->resolveType('UUID'));
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
        $scope->registerTypeAlias($this->makeTypeAliasNode('UUID'));
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
        $alias = $this->makeTypeAliasNode('UUID');
        $parent->registerTypeAlias($alias);

        $child = new TypeScope($parent);
        $this->assertSame($alias, $child->resolveType('UUID'));
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
        $parentAlias = $this->makeTypeAliasNode('ID');
        $parent->registerTypeAlias($parentAlias);

        $child = new TypeScope($parent);
        $childAlias = $this->makeTypeAliasNode('ID');
        $child->registerTypeAlias($childAlias);

        $this->assertSame($childAlias, $child->resolveType('ID'));
        $this->assertSame($parentAlias, $parent->resolveType('ID'));
    }

    public function testParentHasNameFallback(): void
    {
        $parent = new TypeScope();
        $parent->registerTypeAlias($this->makeTypeAliasNode('UUID'));
        $parent->registerModelName('Base');

        $child = new TypeScope($parent);
        $this->assertTrue($child->hasName('UUID'));
        $this->assertTrue($child->hasName('Base'));
        $this->assertFalse($child->hasName('Missing'));
    }

    public function testGetLocalTypeAliases(): void
    {
        $parent = new TypeScope();
        $parent->registerTypeAlias($this->makeTypeAliasNode('ParentAlias'));

        $child = new TypeScope($parent);
        $childAlias = $this->makeTypeAliasNode('ChildAlias');
        $child->registerTypeAlias($childAlias);

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
