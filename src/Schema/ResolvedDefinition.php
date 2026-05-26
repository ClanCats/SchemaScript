<?php

namespace ClanCats\SchemaScript\Schema;

class ResolvedDefinition
{
    /**
     * @var array<string, ResolvedTypeInfo>
     */
    private array $typeInfo = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $propertyKeys = [];

    /**
     * @var array<string, string>
     */
    private array $publicStructAliasMap;

    /**
     * @param array<string> $mapNames
     */
    public function __construct(
        private Definition $definition,
        array $mapNames = [],
    ) {
        $this->publicStructAliasMap = $definition->getPublicStructAliasMap();

        foreach ($definition->getTypeAliases() as $name => $alias) {
            $this->typeInfo[$name] = new ResolvedTypeInfo(
                $alias->isPublic(),
                $alias->getAnnotations()->getLangTypes(),
                $alias->getResolvedType(),
            );
        }

        foreach ($definition->getStructs() as $struct) {
            $this->precomputePropertyKeys($struct, $mapNames);
        }
    }

    /**
     * @param array<string> $mapNames
     */
    private function precomputePropertyKeys(Struct $struct, array $mapNames): void
    {
        foreach ($struct->getProperties() as $prop) {
            $keyMap = [];
            foreach ($mapNames as $mapName) {
                $keyMap[$mapName] = MappingStrategyResolver::resolve(
                    $this->definition,
                    $mapName,
                    $prop->getName(),
                    $prop->getAnnotations()
                );
            }
            $this->propertyKeys[$struct->getName() . '.' . $prop->getName()] = $keyMap;
        }
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    public function getTypeInfo(string $aliasName): ?ResolvedTypeInfo
    {
        return $this->typeInfo[$aliasName] ?? null;
    }

    public function getResolvedKey(string $structName, string $propertyName, string $mapName): string
    {
        $key = $structName . '.' . $propertyName;
        return $this->propertyKeys[$key][$mapName] ?? $propertyName;
    }

    /**
     * @return array{string, string}
     */
    public function getResolvedReadWriteKeys(string $structName, string $propertyName, string $mapFrom, string $mapTo, bool $invert): array
    {
        $fromKey = $this->getResolvedKey($structName, $propertyName, $mapFrom);
        $toKey = $this->getResolvedKey($structName, $propertyName, $mapTo);

        if ($invert) {
            return [$toKey, $fromKey];
        }

        return [$fromKey, $toKey];
    }

    /**
     * @return array<string, string>
     */
    public function getPublicStructAliasMap(): array
    {
        return $this->publicStructAliasMap;
    }

    public function getStruct(string $name): ?Struct
    {
        return $this->definition->getStruct($name);
    }

    /**
     * @return array<string, Struct>
     */
    public function getModels(): array
    {
        return $this->definition->getModels();
    }

    /**
     * @return array<MetadataEntry>
     */
    public function getMetadata(): array
    {
        return $this->definition->getMetadata();
    }

    /**
     * @return array<string, TypeAlias>
     */
    public function getPublicTypeAliases(): array
    {
        return $this->definition->getPublicTypeAliases();
    }

    public function isTypeAliasPublic(string $name): bool
    {
        $info = $this->typeInfo[$name] ?? null;
        return $info !== null && $info->isPublic();
    }
}
