<?php

namespace ClanCats\SchemaScript\Parser;

use ClanCats\SchemaScript\Token as T;
use ClanCats\SchemaScript\TokenType;use ClanCats\SchemaScript\Node\BaseNode;
use ClanCats\SchemaScript\Node\MetadataEntryNode;

class MetadataParser extends SchemaParser
{
    protected ?MetadataEntryNode $metadata = null;

    protected function next(): void
    {
        $key = $this->expectCurrentType(TokenType::MetadataKey)->getValue();
        $this->skipToken();

        $this->expectCurrentType(TokenType::Equal);
        $this->skipToken();

        /** @var BaseNode $value */
        $value = $this->parseChild(MetadataValueParser::class);
        $this->metadata = new MetadataEntryNode($key, $value);
        $this->finish();
    }

    protected function node(): BaseNode
    {
        if ($this->metadata === null) {
            throw $this->errorParsing("Expected a metadata assignment.");
        }

        return $this->metadata;
    }
}
