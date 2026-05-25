<?php

namespace ClanCats\SchemaScript\Importer;

use ClanCats\SchemaScript\Importer\Samg\SamgImporter;
use ClanCats\SchemaScript\Schema\MappingStrategyResolver;
use PHPUnit\Framework\TestCase;

class SamgImporterTest extends TestCase
{
    private SamgImporter $importer;

    protected function setUp(): void
    {
        $this->importer = new SamgImporter();
    }

    public function testGetName(): void
    {
        $this->assertSame('samg', $this->importer->getName());
    }

    public function testImportSimple(): void
    {
        $definition = $this->importer->import(__DIR__ . '/../fixtures/samg/simple.md');

        $models = $definition->getModels();
        $this->assertArrayHasKey('User', $models);
        $this->assertArrayHasKey('Profile', $models);
    }

    public function testImportGenerateBlock(): void
    {
        $definition = $this->importer->import(__DIR__ . '/../fixtures/samg/simple.md');

        $generateConfig = $definition->findMetadataValue('generate');
        $this->assertNotNull($generateConfig);
        $this->assertIsArray($generateConfig);

        $genEntry = $generateConfig[0];
        $this->assertSame('php.samg', $genEntry->getKey());

        $options = $genEntry->getValue();
        $this->assertIsArray($options);

        $optionKeys = array_map(fn($e) => $e->getKey(), $options);
        $this->assertContains('output', $optionKeys);
        $this->assertContains('namespace', $optionKeys);
        $this->assertContains('map_from', $optionKeys);
        $this->assertContains('map_to', $optionKeys);
    }

    public function testImportPropertyTypes(): void
    {
        $definition = $this->importer->import(__DIR__ . '/../fixtures/samg/simple.md');

        $user = $definition->getStruct('User');
        $this->assertNotNull($user);

        $props = $user->getProperties();
        $this->assertSame('id', $props[0]->getName());
        $this->assertTrue($props[0]->getType()->isSimple());
        $this->assertSame('int', $props[0]->getType()->getName());

        $this->assertSame('email', $props[3]->getName());
        $this->assertTrue($props[3]->getType()->isNullable());
    }

    public function testImportPropertyMapping(): void
    {
        $definition = $this->importer->import(__DIR__ . '/../fixtures/samg/simple.md');

        $profile = $definition->getStruct('Profile');
        $this->assertNotNull($profile);

        $props = $profile->getProperties();

        // user_id -> userId: snake_case matches, so no @map.api annotation
        $userIdProp = $props[0];
        $this->assertSame('userId', $userIdProp->getName());
        $this->assertNull($userIdProp->getAnnotations()->getMapKey('api'));

        // display_name -> displayName: snake_case matches, so no @map.api annotation
        $displayNameProp = $props[1];
        $this->assertSame('displayName', $displayNameProp->getName());
        $this->assertNull($displayNameProp->getAnnotations()->getMapKey('api'));

        // is_su -> isAdmin: snake_case of isAdmin = is_admin != is_su, so @map.api is kept
        $isAdminProp = $props[2];
        $this->assertSame('isAdmin', $isAdminProp->getName());
        $this->assertSame('is_su', $isAdminProp->getAnnotations()->getMapKey('api'));

        // bio has no mapping at all
        $bioProp = $props[3];
        $this->assertSame('bio', $bioProp->getName());
        $this->assertNull($bioProp->getAnnotations()->getMapKey('api'));
    }

    public function testImportRelationships(): void
    {
        $definition = $this->importer->import(__DIR__ . '/../fixtures/samg/relationships.md');

        $person = $definition->getStruct('Person');
        $this->assertNotNull($person);
        $props = $person->getProperties();

        $jobProp = $props[2];
        $this->assertSame('job', $jobProp->getName());
        $this->assertTrue($jobProp->getType()->isReference());
        $this->assertSame('Job', $jobProp->getType()->getName());

        $managerProp = $props[3];
        $this->assertTrue($managerProp->getType()->isNullable());
        $inner = $managerProp->getType()->getInnerType();
        $this->assertNotNull($inner);
        $this->assertTrue($inner->isReference());
        $this->assertSame('Person', $inner->getName());

        $commentsProp = $props[4];
        $this->assertTrue($commentsProp->getType()->isArray());
        $inner = $commentsProp->getType()->getInnerType();
        $this->assertNotNull($inner);
        $this->assertTrue($inner->isReference());
        $this->assertSame('Comment', $inner->getName());
    }

    public function testImportFileNotFound(): void
    {
        $this->expectException(\ClanCats\SchemaScript\Exception\ImporterException::class);
        $this->importer->import('/nonexistent/file.md');
    }

    public function testResolveMapKey(): void
    {
        $definition = $this->importer->import(__DIR__ . '/../fixtures/samg/simple.md');

        $profile = $definition->getStruct('Profile');
        $this->assertNotNull($profile);
        $props = $profile->getProperties();

        $resolved = MappingStrategyResolver::resolve($definition, 'api', $props[0]->getName(), $props[0]->getAnnotations());
        $this->assertSame('user_id', $resolved);

        $resolved = MappingStrategyResolver::resolve($definition, 'self', $props[0]->getName(), $props[0]->getAnnotations());
        $this->assertSame('userId', $resolved);
    }
}
