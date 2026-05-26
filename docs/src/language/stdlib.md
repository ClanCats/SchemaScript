# Standard Library

The standard library (`scsc/base`) provides built-in types, mapping strategies, and language-specific type constants. Import it with:

```scsc
import scsc/base
```

## Built-in Types

### Integer Types

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `int` | `int` | `number` |
| `int8` | `int` | `number` |
| `int16` | `int` | `number` |
| `int32` | `int` | `number` |
| `int64` | `int` | `number` |

### Unsigned Integer Types

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `uint` | `int` | `number` |
| `uint8` | `int` | `number` |
| `uint16` | `int` | `number` |
| `uint32` | `int` | `number` |
| `uint64` | `string` | `bigint` |

> **Note:** `uint64` maps to `string` in PHP and `bigint` in TypeScript because 64-bit unsigned integers exceed the safe integer range of PHP's `int` and JavaScript's `number`.

### Floating Point Types

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `float` | `float` | `number` |
| `float32` | `float` | `number` |
| `float64` | `float` | `number` |
| `double` | `float` | `number` |

### Text & Binary

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `string` | `string` | `string` |
| `bytes` | `string` | `Uint8Array` |

### Boolean

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `bool` | `bool` | `boolean` |

### Identifiers

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `uuid` | `string` | `string` |

### Temporal Types

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `timestamp` | `string` | `string` |
| `datetime` | `string` | `string` |
| `date` | `string` | `string` |
| `time` | `string` | `string` |

### Dynamic Types

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `any` | `mixed` | `unknown` |
| `mixed` | `mixed` | `unknown` |

### Collections

| SchemaScript | PHP Type | TypeScript Type |
|-------------|----------|-----------------|
| `map<K, V>` | `array` | `Record<K, V>` |

`map<K, V>` is a generic key-value mapping type. See [Generics](./generics.md) for details.

## Mapping Strategies

The `MappingStrategy` namespace provides constants for property name conversion:

| Constant | Example |
|----------|---------|
| `MappingStrategy::camelCase` | `myProperty` |
| `MappingStrategy::pascalCase` | `MyProperty` |
| `MappingStrategy::snakeCase` | `my_property` |
| `MappingStrategy::screamingSnakeCase` | `MY_PROPERTY` |
| `MappingStrategy::kebabCase` | `my-property` |

Usage:

```scsc
[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}
```

See [Property Mapping](../toolchain/property-mapping.md) for details.

## Language Type Constants

The standard library also provides namespace constants for native language types. These are used internally by type alias annotations:

**PHP types** (`SCSC::Lang::PHP::Type::*`): `int`, `string`, `bool`, `float`, `array`, `object`, `null`, `mixed`

**TypeScript types** (`SCSC::Lang::TS::Type::*`): `number`, `string`, `boolean`, `any`, `null`, `undefined`, `object`, `bigint`, `Uint8Array`, `unknown`

You can reference these in your own `@lang.php` and `@lang.ts` annotations:

```scsc
[type] = {
  @lang.php(SCSC::Lang::PHP::Type::int)
  @lang.ts(SCSC::Lang::TS::Type::number)
  myCustomInt
}
```
