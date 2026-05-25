<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\GeneratorRegistryFactory;
use ClanCats\SchemaScript\Writer\ScscWriter;

class ImportCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        if (count($args) < 2) {
            fwrite(STDERR, "Usage: scsc import <importer> <file> [--output=<file.scsc>] [--stdout]\n");
            return 1;
        }

        $importerName = $args[0];
        $file = $args[1];

        $outputFile = null;
        $toStdout = false;

        for ($i = 2; $i < count($args); $i++) {
            if (str_starts_with($args[$i], '--output=')) {
                $outputFile = substr($args[$i], 9);
            } elseif ($args[$i] === '--stdout') {
                $toStdout = true;
            }
        }

        if (!file_exists($file)) {
            fwrite(STDERR, "Error: File not found: {$file}\n");
            return 1;
        }

        $importerRegistry = GeneratorRegistryFactory::createImporterRegistry();

        if (!$importerRegistry->has($importerName)) {
            fwrite(STDERR, "Error: Unknown importer '{$importerName}'. Available: " . implode(', ', $importerRegistry->getNames()) . "\n");
            return 1;
        }

        try {
            $importer = $importerRegistry->get($importerName);
            $definition = $importer->import($file);
        } catch (\Exception $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            return 1;
        }

        $writer = new ScscWriter();
        $scscOutput = $writer->write($definition);

        if ($outputFile !== null) {
            file_put_contents($outputFile, $scscOutput);
            echo "  wrote {$outputFile}\n";
        } else {
            echo $scscOutput;
        }

        return 0;
    }
}
