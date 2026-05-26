<?php

namespace IntegrationEx\Mappers;

class MessageMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];

        $result['id'] = (string) $data['id'];

        $result['type'] = $data['type'];

        $result['text'] = ($data['text'] !== null ? (string) $data['text'] : null);

        $result['actor'] = ($data['actor'] !== null ? UserMapper::fromArray($data['actor']) : null);

        if (array_key_exists('unseen', $data)) {
            $result['unseen'] = (bool) $data['unseen'];
        }

        $result['payload'] = array_map(fn($v) => (string) $v, $data['payload']);

        $result['createdAt'] = (int) $data['created_at'];

        $result['updatedAt'] = (int) $data['modified_at'];

        return $result;
    }

    public static function toArray(array $data): array
    {
        $result = [];

        $result['id'] = (string) $data['id'];

        $result['type'] = $data['type'];

        $result['text'] = ($data['text'] !== null ? (string) $data['text'] : null);

        $result['actor'] = ($data['actor'] !== null ? UserMapper::toArray($data['actor']) : null);

        if (array_key_exists('unseen', $data)) {
            $result['unseen'] = (bool) $data['unseen'];
        }

        $result['payload'] = array_map(fn($v) => (string) $v, $data['payload']);

        $result['created_at'] = (int) $data['createdAt'];

        $result['modified_at'] = (int) $data['updatedAt'];

        return $result;
    }
}
