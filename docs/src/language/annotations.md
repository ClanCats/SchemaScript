# Annotations

Annotations add metadata to properties, type aliases, and metadata entries using `@name` or `@name(args)` syntax.

## Syntax

```scsc
@local(avatarImageId)
avatar_image_id: int?

@enum("text", "image", "video")
type: string
```

### Dotted Names

Annotation names can be dotted for namespacing:

```scsc
@lang.php('int')
@lang.ts('number')
@map.api(field_name)
```

### Arguments

Arguments are comma-separated and can be strings, numbers, or identifiers:

```scsc
@name('string argument')
@name("double quoted string")
@name(42)
@name(SomeIdentifier)
@name('first', 'second', 'third')
```

## Where Annotations Can Appear

### On Properties

```scsc
User {
  @local(avatarImageId)
  avatar_image_id: int?

  @map.api(modified_at)
  updatedAt: int
}
```

### On Type Aliases

```scsc
[type] = {
  @lang.php('int')
  @lang.ts('bigint')
  int64
}
```

### On Metadata Entries

```scsc
[upgrades] = {
  @source('Asgard')
  [asgard_core] = 'Complete Asgard knowledge base'
}
```

## Known Annotations Reference

| Annotation | Arguments | Purpose |
|------------|-----------|---------|
| `@local(name)` | Identifier or string | Override the local property name used in PHP mappers |
| `@enum(values...)` | Strings | Constrain a property to specific string values |
| `@lang.php(type)` | String or namespace ref | Override the PHP type for a type alias |
| `@lang.ts(type)` | String or namespace ref | Override the TypeScript type for a type alias |
| `@map.<name>(key)` | Identifier or string | Override the property key for a specific mapping context |

### `@local(name)`

Explicitly sets the local property name, overriding any automatic naming:

```scsc
@local(avatarImageId)
avatar_image_id: int?
```

In PHP mappers, the local key will be `avatarImageId` regardless of the mapping strategy.

### `@enum(values...)`

Documents the allowed values for a string property:

```scsc
@enum("text", "image", "video")
type: string
```

### `@lang.php(type)` and `@lang.ts(type)`

Override the target language type for a type alias. These are primarily used in the standard library to map SchemaScript types to native types:

```scsc
[type] = {
  @lang.php('int')
  @lang.ts('number')
  int32

  @lang.php('string')
  @lang.ts('bigint')
  uint64
}
```

### `@map.<name>(key)`

Override the property key for a specific mapping context:

```scsc
Message {
  @map.api(modified_at)
  updatedAt: int
}
```

When the `api` mapping is applied, this property will use the key `modified_at` instead of the automatically converted name. See [Property Mapping](../toolchain/property-mapping.md) for details.
