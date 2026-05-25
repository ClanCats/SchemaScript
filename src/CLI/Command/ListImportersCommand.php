<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\GeneratorRegistryFactory;

class ListImportersCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $registry = GeneratorRegistryFactory::createImporterRegistry();

        echo "Available importers:\n\n";
        foreach ($registry->getAll() as $name => $importer) {
            echo "  {$name}\n";
            echo "    {$importer->getDescription()}\n\n";
        }

        return 0;
    }
}
