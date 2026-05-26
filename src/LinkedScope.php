<?php

namespace ClanCats\SchemaScript;

use ClanCats\SchemaScript\Node\ScopeNode;

class LinkedScope
{
    /**
     * @param array<string, string> $sourceCodeMap
     */
    public function __construct(
        private ScopeNode $scope,
        private array $sourceCodeMap = [],
    ) {}

    public function getScope(): ScopeNode
    {
        return $this->scope;
    }

    /**
     * @return array<string, string>
     */
    public function getSourceCodeMap(): array
    {
        return $this->sourceCodeMap;
    }
}
