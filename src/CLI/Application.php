<?php

namespace ClanCats\SchemaScript\CLI;

use ClanCats\SchemaScript\CLI\Command\ListGeneratorsCommand;
use ClanCats\SchemaScript\CLI\Command\ListImportersCommand;
use ClanCats\SchemaScript\CLI\Command\BuildCommand;
use ClanCats\SchemaScript\CLI\Command\GenerateCommand;
use ClanCats\SchemaScript\CLI\Command\ImportCommand;
use ClanCats\SchemaScript\CLI\Command\AstCommand;
use ClanCats\SchemaScript\CLI\Command\ParseCommand;

class Application
{
    /** @var array<string, CommandInterface> */
    private array $commands;

    public function __construct()
    {
        $this->commands = [
            'build'  => new BuildCommand(),
            'gen'    => new GenerateCommand(),
            'import' => new ImportCommand(),
            'ast'    => new AstCommand(),
        ];
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        if (in_array('--list-gen', $argv, true)) {
            return (new ListGeneratorsCommand())->execute([]);
        }

        if (in_array('--list-import', $argv, true)) {
            return (new ListImportersCommand())->execute([]);
        }

        if (count($argv) < 2) {
            $this->printUsage();
            return 1;
        }

        $commandName = $argv[1];

        if (isset($this->commands[$commandName])) {
            return $this->commands[$commandName]->execute(array_slice($argv, 2));
        }

        return (new ParseCommand())->execute(array_slice($argv, 1));
    }

    private function printUsage(): void
    {
        fwrite(STDERR, "Usage: scsc <file.scsc>\n");
        fwrite(STDERR, "       scsc ast <file.scsc>\n");
        fwrite(STDERR, "       scsc build\n");
        fwrite(STDERR, "       scsc gen <generator> <file.scsc> [--output=<dir>] [--stdout]\n");
        fwrite(STDERR, "       scsc import <importer> <file> [--output=<file.scsc>] [--stdout]\n");
    }
}
