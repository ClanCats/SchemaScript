<?php

namespace IntegrationEx\SAMG;

class UserResponseMap
{
    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterface(array $array): array
    {
        return [
            'error' => (string) ($array['error'] ?? null),
            'data' => UserMap::localToInterface($array['data']),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterfaceMapOnly(array $array): array
    {
        return [
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
            'error' => (string) ($array['error'] ?? null),
            'data' => UserMap::interfaceToLocal($array['data']),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocalMapOnly(array $array): array
    {
        return [
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
        if (array_key_exists('error', $array)) {
            $buffer['error'] = (string) ($array['error'] ?? null);
        }
        if (array_key_exists('data', $array)) {
            $buffer['data'] = UserMap::localToInterface($array['data']);
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
        if (array_key_exists('error', $array)) {
            $buffer['error'] = (string) ($array['error'] ?? null);
        }
        if (array_key_exists('data', $array)) {
            $buffer['data'] = UserMap::interfaceToLocal($array['data']);
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
        $array['error'] = (string) ($array['error'] ?? null);
        $array['data'] = UserMap::localToInterface($array['data']);
    }

    /**
     * @param array<mixed> $array
     */
    public static function castInterface(array &$array): void
    {
        $array['error'] = (string) ($array['error'] ?? null);
        $array['data'] = UserMap::localToInterface($array['data']);
    }
}
