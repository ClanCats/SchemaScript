<?php

namespace ClanCats\SchemaScript\Node;

class MetadataBlockNode extends BaseNode
{
    /**
     * @var array<MetadataEntryNode>
     */
    protected array $entries = [];

    /**
     * @param array<MetadataEntryNode> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    /**
     * @return array<MetadataEntryNode>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function addEntry(MetadataEntryNode $entry): void
    {
        $this->entries[] = $entry;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitMetadataBlock($this);
    }
}
