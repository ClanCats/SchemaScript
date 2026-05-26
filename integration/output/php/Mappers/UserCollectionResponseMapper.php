<?php

namespace IntegrationEx\Mappers;

class UserCollectionResponseMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];

        $result['total_count'] = (int) $data['total_count'];

        $result['filtered_count'] = (int) $data['filtered_count'];

        if (array_key_exists('error', $data)) {
            $result['error'] = (string) $data['error'];
        }

        $result['data'] = array_map(fn($v) => UserMapper::fromArray($v), $data['data']);

        return $result;
    }

    public static function toArray(array $data): array
    {
        $result = [];

        $result['total_count'] = (int) $data['total_count'];

        $result['filtered_count'] = (int) $data['filtered_count'];

        if (array_key_exists('error', $data)) {
            $result['error'] = (string) $data['error'];
        }

        $result['data'] = array_map(fn($v) => UserMapper::toArray($v), $data['data']);

        return $result;
    }
}
