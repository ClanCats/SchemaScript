<?php

namespace IntegrationEx\SAMG;

class MessageMap
{
    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    public static function localToInterface(array $array): array
    {
        return [
            'id' => (string) ($array['id'] ?? null),
            'type' => $array['type'] ?? null,
            'text' => (!isset($array['text'])) ? null : (string) ($array['text'] ?? null),
            'actor' => (!isset($array['actor'])) ? null : UserMap::localToInterface($array['actor']),
            'unseen' => (bool) ($array['unseen'] ?? null),
            'payload' => array_map(fn($v) => (string) ($v ?? null), $array['payload'] ?? []),
            'created_at' => (int) ($array['createdAt'] ?? null),
            'modified_at' => (int) ($array['updatedAt'] ?? null),
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
            'type' => $array['type'] ?? null,
            'text' => $array['text'] ?? null,
            'actor' => $array['actor'] ?? null,
            'unseen' => $array['unseen'] ?? null,
            'payload' => $array['payload'] ?? null,
            'created_at' => $array['createdAt'] ?? null,
            'modified_at' => $array['updatedAt'] ?? null,
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
            'type' => $array['type'] ?? null,
            'text' => (!isset($array['text'])) ? null : (string) ($array['text'] ?? null),
            'actor' => (!isset($array['actor'])) ? null : UserMap::interfaceToLocal($array['actor']),
            'unseen' => (bool) ($array['unseen'] ?? null),
            'payload' => array_map(fn($v) => (string) ($v ?? null), $array['payload'] ?? []),
            'createdAt' => (int) ($array['created_at'] ?? null),
            'updatedAt' => (int) ($array['modified_at'] ?? null),
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
            'type' => $array['type'] ?? null,
            'text' => $array['text'] ?? null,
            'actor' => $array['actor'] ?? null,
            'unseen' => $array['unseen'] ?? null,
            'payload' => $array['payload'] ?? null,
            'createdAt' => $array['created_at'] ?? null,
            'updatedAt' => $array['modified_at'] ?? null,
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
        if (array_key_exists('type', $array)) {
            $buffer['type'] = $array['type'] ?? null;
        }
        if (array_key_exists('text', $array)) {
            $buffer['text'] = ($array['text'] === null) ? null : (string) ($array['text'] ?? null);
        }
        if (array_key_exists('actor', $array)) {
            $buffer['actor'] = ($array['actor'] === null) ? null : UserMap::localToInterface($array['actor']);
        }
        if (array_key_exists('unseen', $array)) {
            $buffer['unseen'] = (bool) ($array['unseen'] ?? null);
        }
        if (array_key_exists('payload', $array)) {
            $buffer['payload'] = array_map(fn($v) => (string) ($v ?? null), $array['payload'] ?? []);
        }
        if (array_key_exists('createdAt', $array)) {
            $buffer['created_at'] = (int) ($array['createdAt'] ?? null);
        }
        if (array_key_exists('updatedAt', $array)) {
            $buffer['modified_at'] = (int) ($array['updatedAt'] ?? null);
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
        if (array_key_exists('type', $array)) {
            $buffer['type'] = $array['type'];
        }
        if (array_key_exists('text', $array)) {
            $buffer['text'] = $array['text'];
        }
        if (array_key_exists('actor', $array)) {
            $buffer['actor'] = $array['actor'];
        }
        if (array_key_exists('unseen', $array)) {
            $buffer['unseen'] = $array['unseen'];
        }
        if (array_key_exists('payload', $array)) {
            $buffer['payload'] = $array['payload'];
        }
        if (array_key_exists('createdAt', $array)) {
            $buffer['created_at'] = $array['createdAt'];
        }
        if (array_key_exists('updatedAt', $array)) {
            $buffer['modified_at'] = $array['updatedAt'];
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
        if (array_key_exists('type', $array)) {
            $buffer['type'] = $array['type'] ?? null;
        }
        if (array_key_exists('text', $array)) {
            $buffer['text'] = ($array['text'] === null) ? null : (string) ($array['text'] ?? null);
        }
        if (array_key_exists('actor', $array)) {
            $buffer['actor'] = ($array['actor'] === null) ? null : UserMap::interfaceToLocal($array['actor']);
        }
        if (array_key_exists('unseen', $array)) {
            $buffer['unseen'] = (bool) ($array['unseen'] ?? null);
        }
        if (array_key_exists('payload', $array)) {
            $buffer['payload'] = array_map(fn($v) => (string) ($v ?? null), $array['payload'] ?? []);
        }
        if (array_key_exists('created_at', $array)) {
            $buffer['createdAt'] = (int) ($array['created_at'] ?? null);
        }
        if (array_key_exists('modified_at', $array)) {
            $buffer['updatedAt'] = (int) ($array['modified_at'] ?? null);
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
        if (array_key_exists('type', $array)) {
            $buffer['type'] = $array['type'];
        }
        if (array_key_exists('text', $array)) {
            $buffer['text'] = $array['text'];
        }
        if (array_key_exists('actor', $array)) {
            $buffer['actor'] = $array['actor'];
        }
        if (array_key_exists('unseen', $array)) {
            $buffer['unseen'] = $array['unseen'];
        }
        if (array_key_exists('payload', $array)) {
            $buffer['payload'] = $array['payload'];
        }
        if (array_key_exists('created_at', $array)) {
            $buffer['createdAt'] = $array['created_at'];
        }
        if (array_key_exists('modified_at', $array)) {
            $buffer['updatedAt'] = $array['modified_at'];
        }
        return $buffer;
    }

    /**
     * @param array<mixed> $array
     */
    public static function castLocal(array &$array): void
    {
        $array['id'] = (string) ($array['id'] ?? null);
        $array['type'] = $array['type'] ?? null;
        $array['text'] = (!isset($array['text'])) ? null : (string) ($array['text'] ?? null);
        $array['actor'] = (!isset($array['actor'])) ? null : UserMap::localToInterface($array['actor']);
        $array['unseen'] = (bool) ($array['unseen'] ?? null);
        $array['payload'] = array_map(fn($v) => (string) ($v ?? null), $array['payload'] ?? []);
        $array['createdAt'] = (int) ($array['createdAt'] ?? null);
        $array['updatedAt'] = (int) ($array['updatedAt'] ?? null);
    }

    /**
     * @param array<mixed> $array
     */
    public static function castInterface(array &$array): void
    {
        $array['id'] = (string) ($array['id'] ?? null);
        $array['type'] = $array['type'] ?? null;
        $array['text'] = (!isset($array['text'])) ? null : (string) ($array['text'] ?? null);
        $array['actor'] = (!isset($array['actor'])) ? null : UserMap::localToInterface($array['actor']);
        $array['unseen'] = (bool) ($array['unseen'] ?? null);
        $array['payload'] = array_map(fn($v) => (string) ($v ?? null), $array['payload'] ?? []);
        $array['created_at'] = (int) ($array['created_at'] ?? null);
        $array['modified_at'] = (int) ($array['modified_at'] ?? null);
    }
}
