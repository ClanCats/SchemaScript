<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\GeneratorRegistryFactory;
use ClanCats\SchemaScript\CLI\SchemaFileHelper;
use ClanCats\SchemaScript\ErrorFormatter;
use ClanCats\SchemaScript\Exception\GeneratorException;

class GenerateCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        if (count($args) < 2) {
            fwrite(STDERR, "Usage: scsc gen <generator> <file.scsc> [--output=<dir>] [--stdout]\n");
            return 1;
        }

        $generatorName = $args[0];
        $file = $args[1];

        $outputDir = null;
        $toStdout = false;

        for ($i = 2; $i < count($args); $i++) {
            if (str_starts_with($args[$i], '--output=')) {
                $outputDir = substr($args[$i], 9);
            } elseif ($args[$i] === '--stdout') {
                $toStdout = true;
            }
        }

        $code = SchemaFileHelper::readFile($file);
        if ($code === null) return 1;

        $registry = GeneratorRegistryFactory::createGeneratorRegistry();

        if (!$registry->has($generatorName)) {
            fwrite(STDERR, "Error: Unknown generator '{$generatorName}'. Available: " . implode(', ', $registry->getNames()) . "\n");
            return 1;
        }

        $definition = SchemaFileHelper::evaluate($code, $file);
        if ($definition === null) return 1;

        if ($outputDir === null && !$toStdout) {
            $meta = $definition->getMetadata();
            $entry = $meta['output:php'] ?? null;
            $outputDir = $entry !== null ? (string) $entry->getValue() : null;
        }

        if ($outputDir === null && !$toStdout) {
            fwrite(STDERR, "Error: No output directory specified. Use --output=<dir>, --stdout, or set [output:php] in the schema.\n");
            return 1;
        }

        $generator = $registry->get($generatorName);
        try {
            $result = $generator->generate($definition);
        } catch (GeneratorException $e) {
            fwrite(STDERR, ErrorFormatter::format($e) . "\n");
            return 1;
        }

        if ($toStdout) {
            foreach ($result->getFiles() as $filename => $content) {
                echo "// === {$filename} ===\n";
                echo $content;
                echo "\n";
            }
        } else {
            if (!is_dir((string) $outputDir)) {
                mkdir((string) $outputDir, 0755, true);
            }
            foreach ($result->getFiles() as $filename => $content) {
                $path = rtrim((string) $outputDir, '/') . '/' . $filename;
                file_put_contents($path, $content);
                echo "  wrote {$path}\n";
            }
        }

        return 0;
    }
}
