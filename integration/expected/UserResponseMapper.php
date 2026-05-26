<?php

namespace IntegrationEx\Mappers;

class UserResponseMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];

        $result['data'] = UserMapper::fromArray($data['data']);

        if (array_key_exists('error', $data)) {
            $result['error'] = (string) $data['error'];
        }

        return $result;
    }

    public static function toArray(array $data): array
    {
        $result = [];

        $result['data'] = UserMapper::toArray($data['data']);

        if (array_key_exists('error', $data)) {
            $result['error'] = (string) $data['error'];
        }

        return $result;
    }
}
