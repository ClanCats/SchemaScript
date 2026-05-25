<?php

namespace ClanCats\SchemaScript\CLI;

interface CommandInterface
{
    /**
     * @param list<string> $args
     */
    public function execute(array $args): int;
}
