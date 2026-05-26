# PHP SAMG Generator

The `php.samg` generator produces PHP SAMG (Schema Auto-Mapping Generation) v2 mapper classes with 10 static methods for bidirectional property mapping and type casting.

## Configuration

```scsc
[generate] = {
  [php.samg] = {
    output = 'output/php/SAMG/'
    namespace = 'App\\SAMG\\'
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

**CLI name:** `php.samg`

```bash
vendor/bin/scsc gen php.samg schema.scsc --output=output/samg/
```

## Output Files

One `<Model>Map.php` file per public model. Each class has 10 static methods organized into three categories: full mapping, partial mapping, and in-place casting.

## The 10 Methods

### Full Mapping (4 methods)

These methods map all properties, using `?? null` for missing keys:

| Method | Direction | Type Casting |
|--------|-----------|--------------|
| `localToInterface(array $array): array` | Local -> Interface | Yes |
| `localToInterfaceMapOnly(array $array): array` | Local -> Interface | No |
| `interfaceToLocal(array $array): array` | Interface -> Local | Yes |
| `interfaceToLocalMapOnly(array $array): array` | Interface -> Local | No |

**"MapOnly"** variants only transform property keys without casting values. Useful when the data is already correctly typed and you only need name conversion.

### Partial Mapping (4 methods)

These methods only map properties that exist in the input array, using `array_key_exists` guards. Ideal for PATCH-style updates:

| Method | Direction | Type Casting |
|--------|-----------|--------------|
| `localToPartialInterface(array $array): array` | Local -> Interface | Yes |
| `localToPartialInterfaceMapOnly(array $array): array` | Local -> Interface | No |
| `interfaceToPartialLocal(array $array): array` | Interface -> Local | Yes |
| `interfaceToPartialLocalMapOnly(array $array): array` | Interface -> Local | No |

### In-Place Casting (2 methods)

These methods cast property values in-place by reference, without creating a new array or changing property keys:

| Method | Context |
|--------|---------|
| `castLocal(array &$array): void` | Cast values using local keys |
| `castInterface(array &$array): void` | Cast values using interface keys |

## Example

Given this schema:

```scsc
User {
  id: uint64
  name: string
}
```

**`UserMap.php`:**

```php
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
```

## When to Use Which Method

| Use Case | Method |
|----------|--------|
| API response -> local storage | `interfaceToLocal` |
| Local data -> API request | `localToInterface` |
| PATCH update (only changed fields) | `localToPartialInterface` / `interfaceToPartialLocal` |
| Key rename without casting (pre-casted data) | `*MapOnly` variants |
| Normalize types on existing array | `castLocal` / `castInterface` |
