<?php

namespace ClanCats\SchemaScript\Schema;

interface DefinitionVisitorInterface
{
    public function visitDefinition(Definition $definition): void;

    public function visitStruct(Struct $struct): void;

    public function visitProperty(Struct $struct, StructProperty $property): void;

    public function visitTypeAlias(TypeAlias $alias): void;
}
