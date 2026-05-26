<?php

namespace IntegrationEx\SAMG;

class UserMap
{
    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterface(array $array): array
    {
        return [
            'id' => (string) ($array['id'] ?? null),
            'name' => (string) ($array['name'] ?? null),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterfaceMapOnly(array $array): array
    {
        return [
            'id' => $array['id'] ?? null,
            'name' => $array['name'] ?? null,
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocal(array $array): array
    {
        return [
            'id' => (string) ($array['id'] ?? null),
            'name' => (string) ($array['name'] ?? null),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocalMapOnly(array $array): array
    {
        return [
            'id' => $array['id'] ?? null,
            'name' => $array['name'] ?? null,
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToPartialInterface(array $array): array
    {
        $buffer = [];
        if (array_key_exists('id', $array)) {
            $buffer['id'] = (string) ($array['id'] ?? null);
        }
        if (array_key_exists('name', $array)) {
            $buffer['name'] = (string) ($array['name'] ?? null);
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
        if (array_key_exists('id', $array)) {
            $buffer['id'] = $array['id'];
        }
        if (array_key_exists('name', $array)) {
            $buffer['name'] = $array['name'];
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
        if (array_key_exists('id', $array)) {
            $buffer['id'] = (string) ($array['id'] ?? null);
        }
        if (array_key_exists('name', $array)) {
            $buffer['name'] = (string) ($array['name'] ?? null);
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
        if (array_key_exists('id', $array)) {
            $buffer['id'] = $array['id'];
        }
        if (array_key_exists('name', $array)) {
            $buffer['name'] = $array['name'];
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     */
    public static function castLocal(array &$array): void
    {
        $array['id'] = (string) ($array['id'] ?? null);
        $array['name'] = (string) ($array['name'] ?? null);
    }

    /**
     * @param array<mixed> $array
     */
    public static function castInterface(array &$array): void
    {
        $array['id'] = (string) ($array['id'] ?? null);
        $array['name'] = (string) ($array['name'] ?? null);
    }
}
