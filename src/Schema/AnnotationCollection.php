<?php

namespace ClanCats\SchemaScript\Schema;

class AnnotationCollection
{
    /**
     * @param array<string, Annotation> $annotations
     */
    public function __construct(
        private array $annotations = []
    ) {}

    /**
     * @return array<string, Annotation>
     */
    public function all(): array
    {
        return $this->annotations;
    }

    public function has(string $name): bool
    {
        return isset($this->annotations[$name]);
    }

    public function get(string $name): ?Annotation
    {
        return $this->annotations[$name] ?? null;
    }

    public function getLangType(string $lang): ?string
    {
        return $this->get('lang.' . $lang)?->getFirstArgument();
    }

    /**
     * @return array<string, string>
     */
    public function getLangTypes(): array
    {
        $langTypes = [];
        foreach ($this->annotations as $name => $annotation) {
            if (str_starts_with($name, 'lang.')) {
                $value = $annotation->getFirstArgument();
                if (is_string($value)) {
                    $langTypes[substr($name, 5)] = $value;
                }
            }
        }
        return $langTypes;
    }

    public function getMapKey(string $mapName): ?string
    {
        return $this->get('map.' . $mapName)?->getFirstArgument();
    }

    public function isEmpty(): bool
    {
        return empty($this->annotations);
    }

    /**
     * @return array<string, mixed[]>
     */
    public function toArray(): array
    {
        return array_map(fn(Annotation $a) => $a->getArguments(), $this->annotations);
    }
}
