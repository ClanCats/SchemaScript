# Metadata

Top-level metadata uses bracket syntax to store schema-level configuration:

```scsc
[version] = 1
[name] = 'MySchema'
[enabled] = true
```

## Value Types

Metadata values can be:

| Type | Example |
|------|---------|
| String | `'hello'` or `"hello"` |
| Number | `42`, `3.14` |
| Boolean | `true`, `false` |
| Identifier | `someIdentifier` |
| Namespace reference | `MappingStrategy::snakeCase` |
| Object | `{ key = value }` |
| List | `{'red', 'green', 'blue'}` |

## Metadata Objects vs Key-Value Objects

There are two different object syntaxes that look similar but produce different data structures.

### Metadata Objects

Metadata objects use bracket keys (`[key] = value`). Each entry is an ordered tuple of key, value, and attributes (annotations). They support:

- Annotations on entries
- Dotted key names
- Duplicate keys (preserved as separate entries)

```scsc
[generate] = {
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
  }
  [ts.types] = {
    output = 'output/ts/types/'
  }
}
```

### Key-Value Objects

Key-value objects use plain identifiers (`key = value`). These are simple assignments with no annotation support. Duplicate keys override -- only the last value is kept:

```scsc
[config] = {
  name = 'example'
  nested = {
    deeper = {
      value = 42
    }
  }
}
```

### Mixing Both Forms

Both forms can appear in the same object:

```scsc
[generate] = {
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
  }
  debug = false
}
```

## Metadata Lists

Lists use `{value, value}` syntax:

```scsc
[colors] = {'red', 'green', 'blue'}
```

## Metadata with Annotations

Entries within metadata objects can have annotations:

```scsc
[upgrades] = {
  @source('Asgard')
  [asgard_core] = 'Complete Asgard knowledge base'

  @source('Ancient')
  [zpm] = 'Zero Point Module power augmentation'
}
```

## Common Metadata Blocks

### `[version]`

Schema format version:

```scsc
[version] = 1
```

### `[map]`

Property mapping configuration (see [Property Mapping](../toolchain/property-mapping.md)):

```scsc
[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}
```

### `[generate]`

Generator configuration (see [Build System](../toolchain/build-system.md)):

```scsc
[generate] = {
  [ts.types] = {
    output = 'output/ts/'
  }
}
```

### `[type]`

Type alias definitions (see [Type Aliases](./type-aliases.md)):

```scsc
[type] = {
  pub MessageType = 'text'|'image'|'video'
}
```

## Model-Level Metadata

Metadata can also appear inside model definitions:

```scsc
User {
  [version] = 2
  [map:local] = 'camelCase'

  id: int
  name: string
}
```
