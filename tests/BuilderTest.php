<?php

namespace ClanCats\SchemaScript;

use PHPUnit\Framework\TestCase;
use ClanCats\SchemaScript\CLI\GeneratorRegistryFactory;

class BuilderTest extends TestCase
{
    private function createBuilder(): Builder
    {
        return new Builder(GeneratorRegistryFactory::createGeneratorRegistry());
    }

    private function integrationBaseDir(): string
    {
        return __DIR__ . '/../integration';
    }

    public function testIntegrationBuild(): void
    {
        $builder = $this->createBuilder();
        $schemaFile = __DIR__ . '/../integration/SCHEMA.scsc';
        $baseDir = $this->integrationBaseDir();
        $this->removeDir($baseDir . '/output');

        $written = $builder->build($schemaFile, $baseDir);

        $this->assertArrayHasKey('php.mappers', $written);
        $this->assertNotEmpty($written['php.mappers']);

        $expectedDir = __DIR__ . '/../integration/expected/';
        foreach ($written['php.mappers'] as $filePath) {
            $filename = basename($filePath);
            $expectedFile = $expectedDir . $filename;

            $this->assertFileExists($expectedFile, "Missing expected file: {$filename}");
            $this->assertFileEquals($expectedFile, $filePath, "Generated {$filename} does not match expected output");
        }

        $expectedFiles = glob($expectedDir . '*.php');
        $generatedFiles = array_map('basename', $written['php.mappers']);
        foreach ($expectedFiles as $expectedFile) {
            $this->assertContains(
                basename($expectedFile),
                $generatedFiles,
                "Expected file " . basename($expectedFile) . " was not generated"
            );
        }
    }

    public function testBuildWithoutGenerateBlockThrows(): void
    {
        $builder = $this->createBuilder();
        $tempDir = sys_get_temp_dir() . '/scsc_test_no_generate';
        $this->removeDir($tempDir);
        mkdir($tempDir, 0755, true);

        $tempSchema = $tempDir . '/test.scsc';
        file_put_contents($tempSchema, "import scsc/base\nUser { id: int }");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No [generate] block found');
        $builder->build($tempSchema, $tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
