<?php

namespace ClanCats\SchemaScript\Writer;

use ClanCats\SchemaScript\Schema\Annotation;
use ClanCats\SchemaScript\Schema\AnnotationCollection;
use ClanCats\SchemaScript\Schema\Definition;
use ClanCats\SchemaScript\Schema\MetadataEntry;
use ClanCats\SchemaScript\Schema\Struct;
use ClanCats\SchemaScript\Schema\StructProperty;
use ClanCats\SchemaScript\Schema\Type;
use PHPUnit\Framework\TestCase;

class ScscWriterTest extends TestCase
{
    private ScscWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new ScscWriter();
    }

    public function testWriteImportHeader(): void
    {
        $definition = new Definition();
        $output = $this->writer->write($definition);
        $this->assertStringStartsWith("import scsc/base\n", $output);
    }

    public function testWriteSimpleModel(): void
    {
        $properties = [
            new StructProperty('id', Type::simple('int'), false),
            new StructProperty('name', Type::simple('string'), false),
        ];
        $definition = new Definition([], [], [], ['User' => new Struct('User', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString("User {\n", $output);
        $this->assertStringContainsString('    id: int', $output);
        $this->assertStringContainsString('    name: string', $output);
        $this->assertStringContainsString('}', $output);
    }

    public function testWriteNullableType(): void
    {
        $properties = [
            new StructProperty('email', Type::nullable(Type::simple('string')), false),
        ];
        $definition = new Definition([], [], [], ['User' => new Struct('User', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('    email: string?', $output);
    }

    public function testWriteArrayType(): void
    {
        $properties = [
            new StructProperty('tags', Type::array(Type::simple('string')), false),
        ];
        $definition = new Definition([], [], [], ['User' => new Struct('User', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('    tags: string[]', $output);
    }

    public function testWriteReferenceType(): void
    {
        $properties = [
            new StructProperty('author', Type::reference('User'), false),
        ];
        $definition = new Definition([], [], [], ['Post' => new Struct('Post', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('    author: User', $output);
    }

    public function testWriteAnnotation(): void
    {
        $properties = [
            new StructProperty('updatedAt', Type::simple('int'), false, new AnnotationCollection([
                'map.api' => new Annotation('map.api', ['modified_at']),
            ])),
        ];
        $definition = new Definition([], [], [], ['Post' => new Struct('Post', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString("    @map.api('modified_at')", $output);
        $this->assertStringContainsString('    updatedAt: int', $output);
    }

    public function testWriteMetadata(): void
    {
        $metadata = [
            new MetadataEntry('version', '1', new AnnotationCollection()),
        ];
        $definition = new Definition($metadata);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString("[version] = '1'", $output);
    }

    public function testWriteOptionalProperty(): void
    {
        $properties = [
            new StructProperty('unseen', Type::simple('bool'), true),
        ];
        $definition = new Definition([], [], [], ['Msg' => new Struct('Msg', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('    unseen?: bool', $output);
    }

    public function testWriteGenericType(): void
    {
        $properties = [
            new StructProperty('data', Type::generic('map', [Type::simple('string'), Type::simple('int')]), false),
        ];
        $definition = new Definition([], [], [], ['Config' => new Struct('Config', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('    data: map<string, int>', $output);
    }

    public function testWriteTypeParameter(): void
    {
        $properties = [
            new StructProperty('value', Type::typeParameter('T'), false),
        ];
        $definition = new Definition([], [], [], ['Wrapper' => new Struct('Wrapper', false, $properties, [], new AnnotationCollection(), ['T'])]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('Wrapper<T> {', $output);
        $this->assertStringContainsString('    value: T', $output);
    }

    public function testWriteGenericModelMultipleParams(): void
    {
        $properties = [
            new StructProperty('data', Type::typeParameter('T'), false),
            new StructProperty('error', Type::typeParameter('E'), false),
        ];
        $definition = new Definition([], [], [], ['Result' => new Struct('Result', false, $properties, [], new AnnotationCollection(), ['T', 'E'])]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('Result<T, E> {', $output);
    }

    public function testWriteNestedGenericType(): void
    {
        $innerMap = Type::generic('map', [Type::simple('string'), Type::simple('int')]);
        $properties = [
            new StructProperty('nested', Type::generic('map', [Type::simple('string'), $innerMap]), false),
        ];
        $definition = new Definition([], [], [], ['Config' => new Struct('Config', false, $properties)]);
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('    nested: map<string, map<string, int>>', $output);
    }

    public function testWriteFromImportedSamg(): void
    {
        $importer = new \ClanCats\SchemaScript\Importer\Samg\SamgImporter();
        $definition = $importer->import(__DIR__ . '/../fixtures/samg/simple.md');
        $output = $this->writer->write($definition);

        $this->assertStringContainsString('import scsc/base', $output);
        $this->assertStringContainsString('User {', $output);
        $this->assertStringContainsString('Profile {', $output);
        $this->assertStringContainsString('userId: int', $output);
        $this->assertStringContainsString('email: string?', $output);
        // snake_case strategy handles userId -> user_id, so no @map.api for it
        $this->assertStringNotContainsString("@map.api('user_id')", $output);
        // is_su != is_admin (snake_case of isAdmin), so @map.api is kept
        $this->assertStringContainsString("@map.api('is_su')", $output);
        $this->assertStringContainsString('MappingStrategy::snakeCase', $output);
    }
}
