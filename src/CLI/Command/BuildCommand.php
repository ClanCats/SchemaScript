<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\GeneratorRegistryFactory;
use ClanCats\SchemaScript\Builder;

class BuildCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $cwd = getcwd();
        if ($cwd === false) {
            fwrite(STDERR, "Error: Unable to determine current directory\n");
            return 1;
        }

        $schemaFile = $cwd . '/SCHEMA.scsc';

        if (!file_exists($schemaFile)) {
            fwrite(STDERR, "Error: No SCHEMA.scsc found in current directory\n");
            return 1;
        }

        $registry = GeneratorRegistryFactory::createGeneratorRegistry();
        $builder = new Builder($registry);

        try {
            $written = $builder->build($schemaFile, $cwd);
        } catch (\Exception $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            return 1;
        }

        foreach ($written as $genName => $files) {
            echo "Generator: {$genName}\n";
            foreach ($files as $file) {
                echo "  wrote {$file}\n";
            }
        }

        return 0;
    }
}
