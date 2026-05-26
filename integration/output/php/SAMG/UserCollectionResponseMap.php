<?php

namespace IntegrationEx\SAMG;

class UserCollectionResponseMap
{
    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterface(array $array): array
    {
        return [
            'total_count' => (int) ($array['total_count'] ?? null),
            'filtered_count' => (int) ($array['filtered_count'] ?? null),
            'error' => (string) ($array['error'] ?? null),
            'data' => array_map(fn($v) => UserMap::localToInterface($v), $array['data'] ?? []),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterfaceMapOnly(array $array): array
    {
        return [
            'total_count' => $array['total_count'] ?? null,
            'filtered_count' => $array['filtered_count'] ?? null,
            'error' => $array['error'] ?? null,
            'data' => $array['data'] ?? null,
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocal(array $array): array
    {
        return [
            'total_count' => (int) ($array['total_count'] ?? null),
            'filtered_count' => (int) ($array['filtered_count'] ?? null),
            'error' => (string) ($array['error'] ?? null),
            'data' => array_map(fn($v) => UserMap::interfaceToLocal($v), $array['data'] ?? []),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocalMapOnly(array $array): array
    {
        return [
            'total_count' => $array['total_count'] ?? null,
            'filtered_count' => $array['filtered_count'] ?? null,
            'error' => $array['error'] ?? null,
            'data' => $array['data'] ?? null,
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToPartialInterface(array $array): array
    {
        $buffer = [];
        if (array_key_exists('total_count', $array)) {
            $buffer['total_count'] = (int) ($array['total_count'] ?? null);
        }
        if (array_key_exists('filtered_count', $array)) {
            $buffer['filtered_count'] = (int) ($array['filtered_count'] ?? null);
        }
        if (array_key_exists('error', $array)) {
            $buffer['error'] = (string) ($array['error'] ?? null);
        }
        if (array_key_exists('data', $array)) {
            $buffer['data'] = array_map(fn($v) => UserMap::localToInterface($v), $array['data'] ?? []);
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToPartialInterfaceMapOnly(array $array): array
    {
        $buffer = [];
        if (array_key_exists('total_count', $array)) {
            $buffer['total_count'] = $array['total_count'];
        }
        if (array_key_exists('filtered_count', $array)) {
            $buffer['filtered_count'] = $array['filtered_count'];
        }
        if (array_key_exists('error', $array)) {
            $buffer['error'] = $array['error'];
        }
        if (array_key_exists('data', $array)) {
            $buffer['data'] = $array['data'];
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToPartialLocal(array $array): array
    {
        $buffer = [];
        if (array_key_exists('total_count', $array)) {
            $buffer['total_count'] = (int) ($array['total_count'] ?? null);
        }
        if (array_key_exists('filtered_count', $array)) {
            $buffer['filtered_count'] = (int) ($array['filtered_count'] ?? null);
        }
        if (array_key_exists('error', $array)) {
            $buffer['error'] = (string) ($array['error'] ?? null);
        }
        if (array_key_exists('data', $array)) {
            $buffer['data'] = array_map(fn($v) => UserMap::interfaceToLocal($v), $array['data'] ?? []);
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToPartialLocalMapOnly(array $array): array
    {
        $buffer = [];
        if (array_key_exists('total_count', $array)) {
            $buffer['total_count'] = $array['total_count'];
        }
        if (array_key_exists('filtered_count', $array)) {
            $buffer['filtered_count'] = $array['filtered_count'];
        }
        if (array_key_exists('error', $array)) {
            $buffer['error'] = $array['error'];
        }
        if (array_key_exists('data', $array)) {
            $buffer['data'] = $array['data'];
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     */
    public static function castLocal(array &$array): void
    {
        $array['total_count'] = (int) ($array['total_count'] ?? null);
        $array['filtered_count'] = (int) ($array['filtered_count'] ?? null);
        $array['error'] = (string) ($array['error'] ?? null);
        $array['data'] = array_map(fn($v) => UserMap::localToInterface($v), $array['data'] ?? []);
    }

    /**
     * @param array<mixed> $array
     */
    public static function castInterface(array &$array): void
    {
        $array['total_count'] = (int) ($array['total_count'] ?? null);
        $array['filtered_count'] = (int) ($array['filtered_count'] ?? null);
        $array['error'] = (string) ($array['error'] ?? null);
        $array['data'] = array_map(fn($v) => UserMap::localToInterface($v), $array['data'] ?? []);
    }
}
