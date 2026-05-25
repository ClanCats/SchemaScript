<?php

namespace ClanCats\SchemaScript\Importer;

use ClanCats\SchemaScript\Importer\Samg\SamgParser;
use PHPUnit\Framework\TestCase;

class SamgParserTest extends TestCase
{
    private function parse(string $content): array
    {
        return (new SamgParser())->parse($content);
    }

    public function testParseGlobalConfig(): void
    {
        $result = $this->parse("    :path.maps src/Maps\n    :namespace.maps App\\Maps\n\n## User\n\n    @id int\n");

        $this->assertSame('src/Maps', $result['config']['path.maps']);
        $this->assertSame('App\\Maps', $result['config']['namespace.maps']);
    }

    public function testParseSimpleModel(): void
    {
        $result = $this->parse("## User\n\n    @id int\n    @name string\n");

        $this->assertArrayHasKey('User', $result['models']);
        $this->assertCount(2, $result['models']['User']['properties']);
    }

    public function testParsePropertyWithMapping(): void
    {
        $result = $this->parse("## User\n\n    @first_name: firstName string\n");

        $prop = $result['models']['User']['properties'][0];
        $this->assertSame('first_name', $prop['interfaceName']);
        $this->assertSame('firstName', $prop['localName']);
        $this->assertSame('string', $prop['type']);
        $this->assertFalse($prop['nullable']);
    }

    public function testParsePropertyWithoutMapping(): void
    {
        $result = $this->parse("## User\n\n    @name string\n");

        $prop = $result['models']['User']['properties'][0];
        $this->assertSame('name', $prop['interfaceName']);
        $this->assertSame('name', $prop['localName']);
    }

    public function testParseNullableProperty(): void
    {
        $result = $this->parse("## User\n\n    @email string?\n");

        $prop = $result['models']['User']['properties'][0];
        $this->assertTrue($prop['nullable']);
        $this->assertSame('string', $prop['type']);
    }

    public function testParseRelationship11(): void
    {
        $result = $this->parse("## Person\n\n    @job: job 1:1 Job\n");

        $prop = $result['models']['Person']['properties'][0];
        $this->assertSame('1:1', $prop['relationship']);
        $this->assertSame('Job', $prop['relationshipModel']);
        $this->assertFalse($prop['nullable']);
    }

    public function testParseRelationship1n(): void
    {
        $result = $this->parse("## Person\n\n    @comments: comments 1:n Comment\n");

        $prop = $result['models']['Person']['properties'][0];
        $this->assertSame('1:n', $prop['relationship']);
        $this->assertSame('Comment', $prop['relationshipModel']);
    }

    public function testParseNullableRelationship(): void
    {
        $result = $this->parse("## Person\n\n    @manager: manager 1:1? Person\n");

        $prop = $result['models']['Person']['properties'][0];
        $this->assertSame('1:1', $prop['relationship']);
        $this->assertTrue($prop['nullable']);
    }

    public function testParseBracketPathNotation(): void
    {
        $result = $this->parse("## Error\n\n    @error[message]: errorMessage string\n");

        $prop = $result['models']['Error']['properties'][0];
        $this->assertSame('error.message', $prop['interfaceName']);
        $this->assertSame('errorMessage', $prop['localName']);
    }

    public function testParseMultipleModels(): void
    {
        $result = $this->parse("## User\n\n    @id int\n\n## Profile\n\n    @bio string\n");

        $this->assertArrayHasKey('User', $result['models']);
        $this->assertArrayHasKey('Profile', $result['models']);
    }

    public function testIgnoresH1AndDescriptionText(): void
    {
        $result = $this->parse("# My API Models\n\nSome description text.\n\n## User\n\n    @id int\n");

        $this->assertArrayHasKey('User', $result['models']);
        $this->assertCount(1, $result['models']);
    }

    public function testParseModelLevelConfig(): void
    {
        $result = $this->parse("## Profile\n\n    :namespace Person\n    @id int\n");

        $this->assertSame('Person', $result['models']['Profile']['config']['namespace']);
    }

    public function testParseFixtureSimple(): void
    {
        $content = file_get_contents(__DIR__ . '/../fixtures/samg/simple.md');
        $result = $this->parse($content);

        $this->assertSame('src/Maps', $result['config']['path.maps']);
        $this->assertArrayHasKey('User', $result['models']);
        $this->assertArrayHasKey('Profile', $result['models']);
        $this->assertCount(4, $result['models']['User']['properties']);
        $this->assertCount(4, $result['models']['Profile']['properties']);
    }

    public function testParseFixtureRelationships(): void
    {
        $content = file_get_contents(__DIR__ . '/../fixtures/samg/relationships.md');
        $result = $this->parse($content);

        $this->assertArrayHasKey('Job', $result['models']);
        $this->assertArrayHasKey('Person', $result['models']);
        $this->assertArrayHasKey('Comment', $result['models']);

        $personProps = $result['models']['Person']['properties'];
        $this->assertSame('1:1', $personProps[2]['relationship']);
        $this->assertSame('Job', $personProps[2]['relationshipModel']);
        $this->assertSame('1:1', $personProps[3]['relationship']);
        $this->assertTrue($personProps[3]['nullable']);
        $this->assertSame('1:n', $personProps[4]['relationship']);
        $this->assertSame('Comment', $personProps[4]['relationshipModel']);
    }
}
