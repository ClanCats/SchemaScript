<?php

namespace ClanCats\SchemaScript\CLI;

use ClanCats\SchemaScript\Generator\GeneratorRegistry;
use ClanCats\SchemaScript\Generator\Php\PhpMappersGenerator;
use ClanCats\SchemaScript\Generator\Php\PhpSamgGenerator;
use ClanCats\SchemaScript\Generator\Ts\TsTypesGenerator;
use ClanCats\SchemaScript\Importer\ImporterRegistry;
use ClanCats\SchemaScript\Importer\Samg\SamgImporter;

class GeneratorRegistryFactory
{
    public static function createGeneratorRegistry(): GeneratorRegistry
    {
        $registry = new GeneratorRegistry();
        $registry->register(new PhpMappersGenerator());
        $registry->register(new PhpSamgGenerator());
        $registry->register(new TsTypesGenerator());
        return $registry;
    }

    public static function createImporterRegistry(): ImporterRegistry
    {
        $registry = new ImporterRegistry();
        $registry->register(new SamgImporter());
        return $registry;
    }
}
