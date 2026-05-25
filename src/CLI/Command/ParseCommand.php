<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\SchemaFileHelper;

class ParseCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        if (count($args) < 1) {
            fwrite(STDERR, "Usage: scsc <file.scsc>\n");
            return 1;
        }

        $code = SchemaFileHelper::readFile($args[0]);
        if ($code === null) return 1;

        $definition = SchemaFileHelper::evaluate($code, $args[0]);
        if ($definition === null) return 1;

        $json = (string) json_encode($definition->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo preg_replace_callback('/^ +/m', fn($m) => str_repeat(' ', intdiv(strlen($m[0]), 4)), $json) . "\n";

        return 0;
    }
}
