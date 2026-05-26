<?php

namespace ClanCats\SchemaScript\Schema;

class DefinitionWalker
{
    public function walk(Definition $definition, DefinitionVisitorInterface $visitor): void
    {
        $visitor->visitDefinition($definition);

        foreach ($definition->getTypeAliases() as $alias) {
            $visitor->visitTypeAlias($alias);
        }

        foreach ($definition->getStructs() as $struct) {
            $visitor->visitStruct($struct);

            foreach ($struct->getProperties() as $property) {
                $visitor->visitProperty($struct, $property);
            }
        }
    }
}
