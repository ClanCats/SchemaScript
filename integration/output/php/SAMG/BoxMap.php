<?php

namespace IntegrationEx\SAMG;

class BoxMap
{
    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterface(array $array): array
    {
        return [
            'pos' => positionMap::localToInterface($array['pos']),
            'size' => sizeMap::localToInterface($array['size']),
            'rotation' => (float) ($array['rotation'] ?? null),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterfaceMapOnly(array $array): array
    {
        return [
            'pos' => $array['pos'] ?? null,
            'size' => $array['size'] ?? null,
            'rotation' => $array['rotation'] ?? null,
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocal(array $array): array
    {
        return [
            'pos' => positionMap::interfaceToLocal($array['pos']),
            'size' => sizeMap::interfaceToLocal($array['size']),
            'rotation' => (float) ($array['rotation'] ?? null),
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function interfaceToLocalMapOnly(array $array): array
    {
        return [
            'pos' => $array['pos'] ?? null,
            'size' => $array['size'] ?? null,
            'rotation' => $array['rotation'] ?? null,
        ];
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToPartialInterface(array $array): array
    {
        $buffer = [];
        if (array_key_exists('pos', $array)) {
            $buffer['pos'] = positionMap::localToInterface($array['pos']);
        }
        if (array_key_exists('size', $array)) {
            $buffer['size'] = sizeMap::localToInterface($array['size']);
        }
        if (array_key_exists('rotation', $array)) {
            $buffer['rotation'] = (float) ($array['rotation'] ?? null);
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
        if (array_key_exists('pos', $array)) {
            $buffer['pos'] = $array['pos'];
        }
        if (array_key_exists('size', $array)) {
            $buffer['size'] = $array['size'];
        }
        if (array_key_exists('rotation', $array)) {
            $buffer['rotation'] = $array['rotation'];
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
        if (array_key_exists('pos', $array)) {
            $buffer['pos'] = positionMap::interfaceToLocal($array['pos']);
        }
        if (array_key_exists('size', $array)) {
            $buffer['size'] = sizeMap::interfaceToLocal($array['size']);
        }
        if (array_key_exists('rotation', $array)) {
            $buffer['rotation'] = (float) ($array['rotation'] ?? null);
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
        if (array_key_exists('pos', $array)) {
            $buffer['pos'] = $array['pos'];
        }
        if (array_key_exists('size', $array)) {
            $buffer['size'] = $array['size'];
        }
        if (array_key_exists('rotation', $array)) {
            $buffer['rotation'] = $array['rotation'];
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     */
    public static function castLocal(array &$array): void
    {
        $array['pos'] = positionMap::localToInterface($array['pos']);
        $array['size'] = sizeMap::localToInterface($array['size']);
        $array['rotation'] = (float) ($array['rotation'] ?? null);
    }

    /**
     * @param array<mixed> $array
     */
    public static function castInterface(array &$array): void
    {
        $array['pos'] = positionMap::localToInterface($array['pos']);
        $array['size'] = sizeMap::localToInterface($array['size']);
        $array['rotation'] = (float) ($array['rotation'] ?? null);
    }
}
