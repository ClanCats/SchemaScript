<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Generator\GeneratorRegistry;
use ClanCats\SchemaScript\Node\ScopeNode;
use ClanCats\SchemaScript\Parser\ScopeParser;
use ClanCats\SchemaScript\Schema\SchemaEvaluator;

class Builder
{
    private GeneratorRegistry $registry;

    public function __construct(GeneratorRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return array<string, string[]> Generator name => list of written file paths
     */
    public function build(string $schemaFile, string $baseDir): array
    {
        $code = file_get_contents($schemaFile);
        if ($code === false) {
            throw new \RuntimeException(sprintf('Could not read schema file: %s', $schemaFile));
        }

        $namespace = new SchemaNamespace();
        $namespace->importStdlib();

        $tokens = (new Lexer($code, $schemaFile))->tokens();
        /** @var ScopeNode $scope */
        $scope = (new ScopeParser($tokens))->parse();
        $definition = (new SchemaEvaluator($namespace))->evaluate($scope);

        $generateConfig = $definition->findMetadataValue('generate');
        if ($generateConfig === null) {
            throw new \RuntimeException('No [generate] block found in schema');
        }

        $written = [];

        foreach ($generateConfig as $genEntry) {
            $genName = $genEntry['key'];
            $genOptions = $this->flattenMetadataBlock($genEntry['value'] ?? []);

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
     * @param array<array{key: string, value: mixed, attributes: array<string, mixed[]>}> $entries
     * @return array<string, mixed>
     */
    private function flattenMetadataBlock(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $result[$entry['key']] = $entry['value'];
        }
        return $result;
    }
}
