<?php

namespace ClanCats\SchemaScript\Node;

abstract class BaseNode
{
    abstract public function accept(NodeVisitorInterface $visitor): void;
}
