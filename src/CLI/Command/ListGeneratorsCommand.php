<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\GeneratorRegistryFactory;

class ListGeneratorsCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $registry = GeneratorRegistryFactory::createGeneratorRegistry();

        echo "Available generators:\n\n";
        foreach ($registry->getAll() as $name => $generator) {
            echo "  {$name}\n";
            echo "    {$generator->getDescription()}\n\n";
        }

        return 0;
    }
}
