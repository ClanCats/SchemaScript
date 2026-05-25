<?php

namespace ClanCats\SchemaScript\Importer\Samg;

use ClanCats\SchemaScript\Exception\ImporterException;

class SamgParser
{
    /**
     * @return array{config: array<string, string>, models: array<string, array{config: array<string, string>, properties: list<array{interfaceName: string, localName: string, type: string, nullable: bool, relationship: ?string, relationshipModel: ?string}>}>}
     */
    public function parse(string $content): array
    {
        $lines = explode("\n", $content);

        $globalConfig = [];
        $models = [];
        $currentModel = null;
        $currentModelConfig = [];
        $currentProperties = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                if ($currentModel !== null) {
                    $models[$currentModel] = [
                        'config' => $currentModelConfig,
                        'properties' => $currentProperties,
                    ];
                }
                $currentModel = trim(substr($trimmed, 3));
                $currentModelConfig = [];
                $currentProperties = [];
                continue;
            }

            if (str_starts_with($trimmed, '# ')) {
                continue;
            }

            if (str_starts_with($trimmed, ':')) {
                $parts = preg_split('/\s+/', $trimmed, 2) ?: [$trimmed];
                $key = ltrim($parts[0], ':');
                $value = $parts[1] ?? '';

                if ($currentModel !== null) {
                    $currentModelConfig[$key] = $value;
                } else {
                    $globalConfig[$key] = $value;
                }
                continue;
            }

            if (str_starts_with($trimmed, '@')) {
                $currentProperties[] = $this->parseProperty(substr($trimmed, 1));
                continue;
            }
        }

        if ($currentModel !== null) {
            $models[$currentModel] = [
                'config' => $currentModelConfig,
                'properties' => $currentProperties,
            ];
        }

        return [
            'config' => $globalConfig,
            'models' => $models,
        ];
    }

    /**
     * @return array{interfaceName: string, localName: string, type: string, nullable: bool, relationship: ?string, relationshipModel: ?string}
     */
    private function parseProperty(string $raw): array
    {
        $interfaceName = '';
        $localName = '';
        $type = '';
        $nullable = false;
        $relationship = null;
        $relationshipModel = null;

        $hasMappingColon = (bool) preg_match('/^[a-zA-Z_\[\]0-9]+\s*:/', $raw);

        if ($hasMappingColon) {
            [$left, $right] = explode(':', $raw, 2);
            $interfaceName = $this->normalizeInterfaceName(trim($left));
            $rightParts = preg_split('/\s+/', trim($right)) ?: [];
            $localName = array_shift($rightParts) ?? $interfaceName;
            $typeParts = $rightParts;
        } else {
            $parts = preg_split('/\s+/', trim($raw)) ?: [];
            $interfaceName = $this->normalizeInterfaceName(array_shift($parts) ?? '');
            $localName = $interfaceName;
            $typeParts = $parts;
        }

        if (empty($typeParts)) {
            $type = 'mixed';
        } else {
            $typeStr = $typeParts[0];

            if (preg_match('/^1:(1|n)(\?)?$/', $typeStr, $m)) {
                $relationship = $m[1] === 'n' ? '1:n' : '1:1';
                $nullable = isset($m[2]);
                $relationshipModel = $typeParts[1] ?? null;
                $type = 'reference';
            } else {
                if (str_ends_with($typeStr, '?')) {
                    $nullable = true;
                    $typeStr = substr($typeStr, 0, -1);
                }
                $type = $typeStr;
            }
        }

        return [
            'interfaceName' => $interfaceName,
            'localName' => $localName,
            'type' => $type,
            'nullable' => $nullable,
            'relationship' => $relationship,
            'relationshipModel' => $relationshipModel,
        ];
    }

    private function normalizeInterfaceName(string $name): string
    {
        if (preg_match('/^([a-zA-Z_]+)(\[.+\])$/', $name, $m)) {
            $base = $m[1];
            $path = $m[2];
            $path = str_replace(['[', ']'], ['.', ''], $path);
            $path = trim($path, '.');
            return $base . '.' . $path;
        }

        return $name;
    }
}
