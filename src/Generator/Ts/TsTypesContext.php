<?php

namespace ClanCats\SchemaScript\Generator\Ts;

use ClanCats\SchemaScript\Generator\GeneratorContext;

class TsTypesContext extends GeneratorContext
{
    /**
     * @var array<string, true>
     */
    public array $pubStructNames = [];

    /**
     * @var array<string, string>
     */
    public array $pubStructToAlias = [];

    /**
     * @var array<string, true>
     */
    public array $referencedPubTypes = [];

    /**
     * @var array<string, true>
     */
    public array $referencedStructs = [];

    /**
     * @var string|null Alias for mapFrom, used as the single mapping name for TS types
     */
    public ?string $mapName = null;

    public ?string $currentStructName = null;

    public function resetReferencedPubTypes(): void
    {
        $this->referencedPubTypes = [];
        $this->referencedStructs = [];
    }
}
