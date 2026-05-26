# PHP Mappers Generator

The `php.mappers` generator produces PHP mapper classes with `fromArray` and `toArray` static methods for bidirectional data transformation.

## Configuration

```scsc
[generate] = {
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
    map_from = self
    map_to = api
  }
}
```

### Options

| Option | Required | Description |
|--------|----------|-------------|
| `output` | Yes | Output directory for generated `.php` files |
| `namespace` | No | PHP namespace prefix for generated classes |
| `map_from` | No | Source mapping context (default: `self`) |
| `map_to` | No | Target mapping context |
| `include_comments` | No | Include comments in generated code |

**CLI name:** `php-mappers`

```bash
vendor/bin/scsc gen php-mappers schema.scsc --output=output/php/
```

## Output Files

One `<Model>Mapper.php` file per public model. Each class has two static methods:

- **`fromArray(array $data): array`** -- Converts from the `map_to` context to the `map_from` context (e.g., API data to local format)
- **`toArray(array $data): array`** -- Converts from the `map_from` context to the `map_to` context (e.g., local format to API data)

## Features

### Type Casting

Each property is automatically cast to its PHP type:

```php
$result['id'] = (string) $data['id'];       // uint64 -> string
$result['name'] = (string) $data['name'];    // string -> string
$result['age'] = (int) $data['age'];         // int -> int
$result['score'] = (float) $data['score'];   // float -> float
$result['active'] = (bool) $data['active'];  // bool -> bool
```

### Nullable Properties

Nullable types include a null check:

```php
$result['text'] = ($data['text'] !== null ? (string) $data['text'] : null);
$result['actor'] = ($data['actor'] !== null ? UserMapper::fromArray($data['actor']) : null);
```

### Optional Keys

Optional keys (`name?:`) are guarded with `array_key_exists`:

```php
if (array_key_exists('unseen', $data)) {
    $result['unseen'] = (bool) $data['unseen'];
}
```

### Nested Model References

Properties referencing other models delegate to their mapper:

```php
$result['actor'] = UserMapper::fromArray($data['actor']);
$result['actor'] = ($data['actor'] !== null ? UserMapper::fromArray($data['actor']) : null);
```

### Map Types

`map<K, V>` properties use `array_map()` for value casting:

```php
$result['payload'] = array_map(fn($v) => (string) $v, $data['payload']);
$result['scores'] = array_map(fn($v) => (int) $v, $data['scores']);
```

### Property Name Mapping

When `map_from` and `map_to` differ, property keys are converted:

```php
// fromArray: api keys -> local keys
$result['createdAt'] = (int) $data['created_at'];

// toArray: local keys -> api keys
$result['created_at'] = (int) $data['createdAt'];
```

## Full Example

Given this schema:

```scsc
Message {
  id: uint64
  type: MessageType
  text: string?
  actor: User?
  unseen?: bool
  payload: map<string, string>
  createdAt: int
  @map.api(modified_at)
  updatedAt: int
}
```

**`MessageMapper.php`:**

```php
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
```
