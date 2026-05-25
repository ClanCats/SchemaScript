<?php

namespace ClanCats\SchemaScript\Generator;

use ClanCats\SchemaScript\Generator\Php\PhpSamgGenerator;
use ClanCats\SchemaScript\Importer\Samg\SamgImporter;
use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;
use PHPUnit\Framework\TestCase;

class PhpSamgGeneratorTest extends TestCase
{
    private PhpSamgGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PhpSamgGenerator();
    }

    public function testGetName(): void
    {
        $this->assertSame('php.samg', $this->generator->getName());
    }

    public function testGenerateSimpleModel(): void
    {
        $definition = $this->buildSimpleDefinition();
        $result = $this->generator->generate($definition, ['namespace' => 'App\\Maps']);

        $files = $result->getFiles();
        $this->assertArrayHasKey('UserMap.php', $files);

        $code = $files['UserMap.php'];
        $this->assertStringContainsString('namespace App\\Maps;', $code);
        $this->assertStringContainsString('class UserMap', $code);
    }

    public function testGenerateAllTenMethods(): void
    {
        $definition = $this->buildSimpleDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['UserMap.php'];

        $methods = [
            'localToInterface',
            'localToInterfaceMapOnly',
            'interfaceToLocal',
            'interfaceToLocalMapOnly',
            'localToPartialInterface',
            'localToPartialInterfaceMapOnly',
            'interfaceToPartialLocal',
            'interfaceToPartialLocalMapOnly',
            'castLocal',
            'castInterface',
        ];

        foreach ($methods as $method) {
            $this->assertStringContainsString("function {$method}(", $code, "Missing method: {$method}");
        }
    }

    public function testLocalToInterfaceMapping(): void
    {
        $definition = $this->buildMappedDefinition();
        $result = $this->generator->generate($definition, ['map_from' => 'self', 'map_to' => 'api']);
        $code = $result->getFiles()['ProfileMap.php'];

        $this->assertStringContainsString("'user_id' => (int) (\$array['userId']", $code);
        $this->assertStringContainsString("'display_name' => (string) (\$array['displayName']", $code);
    }

    public function testInterfaceToLocalMapping(): void
    {
        $definition = $this->buildMappedDefinition();
        $result = $this->generator->generate($definition, ['map_from' => 'self', 'map_to' => 'api']);
        $code = $result->getFiles()['ProfileMap.php'];

        $this->assertStringContainsString("'userId' => (int) (\$array['user_id']", $code);
        $this->assertStringContainsString("'displayName' => (string) (\$array['display_name']", $code);
    }

    public function testMapOnlyNoTypeCasting(): void
    {
        $definition = $this->buildSimpleDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['UserMap.php'];

        $this->assertMatchesRegularExpression('/localToInterfaceMapOnly.*?return \[.*?\$array\[\'id\'\] \?\? null/s', $code);
    }

    public function testNullableHandling(): void
    {
        $definition = $this->buildNullableDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['UserMap.php'];

        $this->assertStringContainsString('!isset(', $code);
        $this->assertStringContainsString('? null :', $code);
    }

    public function testPartialMappingUsesIsset(): void
    {
        $definition = $this->buildSimpleDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['UserMap.php'];

        $this->assertStringContainsString('$buffer = [];', $code);
        $this->assertStringContainsString("array_key_exists(", $code);
    }

    public function testCastMethodsByReference(): void
    {
        $definition = $this->buildSimpleDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['UserMap.php'];

        $this->assertStringContainsString('function castLocal(array &$array): void', $code);
        $this->assertStringContainsString('function castInterface(array &$array): void', $code);
    }

    public function testRelationshipDelegation(): void
    {
        $definition = $this->buildRelationshipDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['PersonMap.php'];

        $this->assertStringContainsString('JobMap::localToInterface(', $code);
        $this->assertStringContainsString('JobMap::interfaceToLocal(', $code);
    }

    public function testArrayRelationshipDelegation(): void
    {
        $definition = $this->buildArrayRelationshipDefinition();
        $result = $this->generator->generate($definition);
        $code = $result->getFiles()['PersonMap.php'];

        $this->assertStringContainsString('array_map(fn($v) => CommentMap::localToInterface($v)', $code);
    }

    public function testGenerateFromImportedSamg(): void
    {
        $importer = new SamgImporter();
        $definition = $importer->import(__DIR__ . '/../fixtures/samg/simple.md');

        $result = $this->generator->generate($definition, [
            'namespace' => 'App\\Maps',
            'map_from' => 'self',
            'map_to' => 'api',
        ]);

        $files = $result->getFiles();
        $this->assertArrayHasKey('UserMap.php', $files);
        $this->assertArrayHasKey('ProfileMap.php', $files);

        $profileCode = $files['ProfileMap.php'];
        $this->assertStringContainsString("'user_id' => (int)", $profileCode);
        $this->assertStringContainsString("'display_name' => (string)", $profileCode);
    }

    private function buildSimpleDefinition(): Definition
    {
        $properties = [
            new StructProperty('id', Type::simple('int'), false),
            new StructProperty('name', Type::simple('string'), false),
        ];
        $struct = new Struct('User', false, $properties);
        return new Definition([], [], [], ['User' => $struct]);
    }

    private function buildMappedDefinition(): Definition
    {
        $properties = [
            new StructProperty('userId', Type::simple('int'), false, new AnnotationCollection([
                'map.api' => new Annotation('map.api', ['user_id']),
            ])),
            new StructProperty('displayName', Type::simple('string'), false, new AnnotationCollection([
                'map.api' => new Annotation('map.api', ['display_name']),
            ])),
        ];
        $struct = new Struct('Profile', false, $properties);
        return new Definition([], [], [], ['Profile' => $struct]);
    }

    private function buildNullableDefinition(): Definition
    {
        $properties = [
            new StructProperty('email', Type::nullable(Type::simple('string')), false),
        ];
        $struct = new Struct('User', false, $properties);
        return new Definition([], [], [], ['User' => $struct]);
    }

    private function buildRelationshipDefinition(): Definition
    {
        $jobProps = [new StructProperty('name', Type::simple('string'), false)];
        $personProps = [
            new StructProperty('name', Type::simple('string'), false),
            new StructProperty('job', Type::reference('Job'), false),
        ];
        return new Definition([], [], [], [
            'Job' => new Struct('Job', false, $jobProps),
            'Person' => new Struct('Person', false, $personProps),
        ]);
    }

    private function buildArrayRelationshipDefinition(): Definition
    {
        $commentProps = [new StructProperty('text', Type::simple('string'), false)];
        $personProps = [
            new StructProperty('name', Type::simple('string'), false),
            new StructProperty('comments', Type::array(Type::reference('Comment')), false),
        ];
        return new Definition([], [], [], [
            'Comment' => new Struct('Comment', false, $commentProps),
            'Person' => new Struct('Person', false, $personProps),
        ]);
    }
}
