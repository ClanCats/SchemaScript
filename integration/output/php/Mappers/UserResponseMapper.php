<?php

namespace IntegrationEx\Mappers;

class UserResponseMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];

        if (array_key_exists('error', $data)) {
            $result['error'] = (string) $data['error'];
        }

        $result['data'] = UserMapper::fromArray($data['data']);

        return $result;
    }

    public static function toArray(array $data): array
    {
        $result = [];

        if (array_key_exists('error', $data)) {
            $result['error'] = (string) $data['error'];
        }

        $result['data'] = UserMapper::toArray($data['data']);

        return $result;
    }
}
