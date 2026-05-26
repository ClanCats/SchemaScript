<?php

namespace IntegrationEx\Mappers;

class UserMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];

        $result['id'] = (string) $data['id'];

        $result['name'] = (string) $data['name'];

        return $result;
    }

    public static function toArray(array $data): array
    {
        $result = [];

        $result['id'] = (string) $data['id'];

        $result['name'] = (string) $data['name'];

        return $result;
    }
}
