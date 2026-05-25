<?php

namespace ClanCats\SchemaScript\Generator\Ts;

class TsTypesContext
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

    public ?string $mapName = null;

    public function resetReferencedPubTypes(): void
    {
        $this->referencedPubTypes = [];
    }
}
