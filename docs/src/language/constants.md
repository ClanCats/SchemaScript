# Constants & Namespaces

Constants are enumeration-style symbols. They can be declared at the root scope or grouped inside namespaces.

## Root-Level Constants

```scsc
const pk_t = uint64
const defaultMapping = MappingType::camelCase
```

Root-level constants with a type alias value (like `pk_t = uint64`) can be used as types in property definitions:

```scsc
const pk_t = uint64

User {
  id: pk_t    // resolves to uint64
}
```

## Namespaces

Constants grouped inside `ns` blocks form a namespace:

```scsc
ns MappingStrategy {
  const camelCase
  const pascalCase
  const snakeCase
  const screamingSnakeCase
  const kebabCase
}

ns Visibility {
  const public
  const private
  const internal
}
```

Namespaces can be nested:

```scsc
ns SCSC {
  ns Lang {
    ns PHP {
      ns Type {
        const int = 'int'
        const string = 'string'
        const bool = 'bool'
      }
    }
  }
}
```

## Referencing Constants

Constants are referenced using `::` syntax:

```scsc
[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}
```

Nested namespaces use chained `::`:

```scsc
@lang.php(SCSC::Lang::PHP::Type::int)
int64
```

## Constants Without Values

Constants declared without an assigned value resolve to their qualified name as a string. For example, `MappingStrategy::camelCase` resolves to the string `"MappingStrategy::camelCase"`:

```scsc
ns Visibility {
  const public      // resolves to "Visibility::public"
  const private     // resolves to "Visibility::private"
}
```

This makes them useful as enum-like symbols in metadata and configuration.

## Standard Library Namespaces

The standard library (`scsc/base`) provides several predefined namespaces:

| Namespace | Constants |
|-----------|-----------|
| `MappingStrategy` | `camelCase`, `pascalCase`, `snakeCase`, `screamingSnakeCase`, `kebabCase` |
| `SCSC::Lang::PHP::Type` | `int`, `string`, `bool`, `float`, `array`, `object`, `null`, `mixed` |
| `SCSC::Lang::TS::Type` | `number`, `string`, `boolean`, `any`, `null`, `undefined`, `object`, `bigint`, `Uint8Array`, `unknown` |

These are used internally by the standard library's type annotations and by generators, but you can also reference them in your own schemas.
