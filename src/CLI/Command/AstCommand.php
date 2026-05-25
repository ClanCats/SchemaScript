<?php

namespace ClanCats\SchemaScript\CLI\Command;

use ClanCats\SchemaScript\CLI\CommandInterface;
use ClanCats\SchemaScript\CLI\SchemaFileHelper;
use ClanCats\SchemaScript\Visitor\ASTPrinter;

class AstCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        if (count($args) < 1) {
            fwrite(STDERR, "Usage: scsc ast <file.scsc>\n");
            return 1;
        }

        $code = SchemaFileHelper::readFile($args[0]);
        if ($code === null) return 1;

        $scope = SchemaFileHelper::parse($code, $args[0]);
        if ($scope === null) return 1;

        $printer = new ASTPrinter();
        echo $printer->print($scope);

        return 0;
    }
}
