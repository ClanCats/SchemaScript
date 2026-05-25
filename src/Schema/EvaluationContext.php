<?php

namespace ClanCats\SchemaScript\Schema;

use ClanCats\SchemaScript\Node\ConstantNode;

class EvaluationContext
{
    /**
     * @var array<string, Struct>
     */
    public array $structs = [];

    /**
     * @var array<string, TypeAlias>
     */
    public array $typeAliasData = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $namespaces = [];

    /**
     * @var array<string, true>
     */
    public array $importedFiles = [];

    /**
     * @var array<string, true>
     */
    public array $resolvingRefs = [];

    /**
     * @var array<string, string>
     */
    public array $identifierConstants = [];

    /**
     * @var array<string, ConstantNode>
     */
    public array $valueConstants = [];

    /**
     * @var array<string, string>
     */
    public array $sourceCodeMap = [];
}
