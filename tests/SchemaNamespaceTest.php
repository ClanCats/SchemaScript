<?php

namespace ClanCats\SchemaScript\Tests;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\SchemaNamespace;
use ClanCats\SchemaScript\Exception\SchemaNamespaceException;

class SchemaNamespaceTest extends TestCase
{
    private function fixtureDir(): string
    {
        return __DIR__ . '/fixtures/imports';
    }

    public function testImportDirectory(): void
    {
        $ns = new SchemaNamespace();
        $ns->importDirectory($this->fixtureDir());

        $this->assertTrue($ns->has('common'));
        $this->assertTrue($ns->has('models/user'));
        $this->assertTrue($ns->has('models/post'));
        $this->assertTrue($ns->has('circular_a'));
        $this->assertTrue($ns->has('circular_b'));
    }

    public function testImportDirectoryWithPrefix(): void
    {
        $ns = new SchemaNamespace();
        $ns->importDirectory($this->fixtureDir() . '/models', 'my');

        $this->assertTrue($ns->has('my/user'));
        $this->assertTrue($ns->has('my/post'));
        $this->assertFalse($ns->has('models/user'));
    }

    public function testGetPath(): void
    {
        $ns = new SchemaNamespace();
        $ns->importDirectory($this->fixtureDir());

        $path = $ns->getPath('common');
        $this->assertStringEndsWith('common.scsc', $path);
        $this->assertFileExists($path);
    }

    public function testGetCode(): void
    {
        $ns = new SchemaNamespace();
        $ns->importDirectory($this->fixtureDir());

        $code = $ns->getCode('common');
        $this->assertStringContainsString('Timestamps', $code);
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $ns = new SchemaNamespace();
        $ns->importDirectory($this->fixtureDir());

        $this->assertFalse($ns->has('nonexistent'));
    }

    public function testGetPathThrowsForUnknown(): void
    {
        $ns = new SchemaNamespace();
        $ns->importDirectory($this->fixtureDir());

        $this->expectException(SchemaNamespaceException::class);
        $this->expectExceptionMessage('Unknown import: "nonexistent"');
        $ns->getPath('nonexistent');
    }

    public function testImportStdlib(): void
    {
        $ns = new SchemaNamespace();
        $ns->importStdlib();

        $this->assertTrue($ns->has('scsc/base'));
        $this->assertStringContainsString('[type]', $ns->getCode('scsc/base'));
    }

    public function testImportDirectoryThrowsForMissingDir(): void
    {
        $ns = new SchemaNamespace();

        $this->expectException(SchemaNamespaceException::class);
        $ns->importDirectory('/nonexistent/path');
    }
}
