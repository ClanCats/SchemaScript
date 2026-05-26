<?php

namespace IntegrationEx\Mappers;

class BoxMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];

        $result['pos'] = ['x' => (int) $data['pos']['x'], 'y' => (int) $data['pos']['y']];

        $result['size'] = ['width' => (int) $data['size']['width'], 'height' => (int) $data['size']['height']];

        $result['rotation'] = (float) $data['rotation'];

        return $result;
    }

    public static function toArray(array $data): array
    {
        $result = [];

        $result['pos'] = ['x' => (int) $data['pos']['x'], 'y' => (int) $data['pos']['y']];

        $result['size'] = ['width' => (int) $data['size']['width'], 'height' => (int) $data['size']['height']];

        $result['rotation'] = (float) $data['rotation'];

        return $result;
    }
}
