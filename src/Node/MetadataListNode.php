<?php

namespace ClanCats\SchemaScript\Node;

class MetadataListNode extends BaseNode
{
    /**
     * @var array<BaseNode>
     */
    protected array $items = [];

    /**
     * @param array<BaseNode> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @return array<BaseNode>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(BaseNode $item): void
    {
        $this->items[] = $item;
    }

    public function accept(NodeVisitorInterface $visitor): void
    {
        $visitor->visitMetadataList($this);
    }
}
