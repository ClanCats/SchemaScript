<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Generator\GeneratorRegistry;
use ClanCats\SchemaScript\Schema\DefinitionValidator;

class Builder
{
    private GeneratorRegistry $registry;
    private SchemaCompiler $compiler;

    public function __construct(GeneratorRegistry $registry, ?SchemaCompiler $compiler = null)
    {
        $this->registry = $registry;
        $this->compiler = $compiler ?? new SchemaCompiler();
    }

    /**
     * @return array<string, string[]> Generator name => list of written file paths
     */
    public function build(string $schemaFile, string $baseDir): array
    {
        $namespace = new SchemaNamespace();
        $namespace->importStdlib();

        $definition = $this->compiler->compile($schemaFile, $namespace);

        $validator = new DefinitionValidator();
        $validator->validate($definition);

        $generateConfig = $definition->findMetadataValue('generate');
        if ($generateConfig === null) {
            throw new \RuntimeException('No [generate] block found in schema');
        }

        $written = [];

        foreach ($generateConfig as $genEntry) {
            $genName = $genEntry->getKey();
            $genOptions = $this->flattenMetadataBlock($genEntry->getValue() ?? []);

            $outputDir = $genOptions['output'] ?? null;
            if ($outputDir === null) {
                throw new \RuntimeException(sprintf("Generator '%s' has no 'output' option", $genName));
            }

            $absoluteOutputDir = rtrim($baseDir, '/') . '/' . ltrim($outputDir, '/');

            $generator = $this->registry->get($genName);
            $result = $generator->generate($definition, $genOptions);

            if (!is_dir($absoluteOutputDir)) {
                mkdir($absoluteOutputDir, 0755, true);
            }

            $written[$genName] = [];
            foreach ($result->getFiles() as $filename => $content) {
                $path = rtrim($absoluteOutputDir, '/') . '/' . $filename;
                file_put_contents($path, $content);
                $written[$genName][] = $path;
            }
        }

        return $written;
    }

    /**
     * @param array<Schema\MetadataEntry> $entries
     * @return array<string, mixed>
     */
    private function flattenMetadataBlock(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $result[$entry->getKey()] = $entry->getValue();
        }
        return $result;
    }
}
