# SchemaScript Syntax

SchemaScript (`.scsc`) is the successor to SAMG. While SAMG used a flat `@property type` syntax in Markdown files, SchemaScript introduces a structured, block-based schema language that supports nested objects, arrays, union types, annotations, and per-model metadata — all in a dedicated file format.

## Global Metadata

Top-level metadata directives use bracket syntax:

```
[version] = 1
[types] = {
  int64
}
```

- `[version]` — schema file version
- `[types]` — declares custom/extended types available in the schema

## Model Definitions

Models are defined as named blocks with curly braces:

```
User {
  ...
}
```

### Model-Level Metadata

Inside a model, bracket directives define model-specific metadata:

```
User {
  [version] = 2
  [map:local] = 'camelCase'
}
```

- `[version]` — model schema version (useful for migrations)
- `[map:local]` — automatic local name mapping strategy (e.g. `'camelCase'` converts `snake_case` API names to camelCase locally, replacing SAMG's explicit `@api_name: localName` syntax)

## Properties

Properties use `name: type` syntax:

```
id: int
```

### Nullable Types

Append `?` to the type to make the value nullable:

```
avatar_image_id: int?
```

### Optional Keys

Append `?` to the key name (before the colon) to make the key optional. An optional key may or may not be present in the data:

```
avatar_image?: {
  data: Image?
}
```

Here `avatar_image?` means the key itself is optional (it may be absent), while `Image?` means when the key *is* present, its value can be null.

### Annotations

Annotations add metadata to properties:

```
@local(avatarImageId)
avatar_image_id: int?
```

- `@local(name)` — explicit local name override (for cases where `[map:local]` isn't sufficient)
- `@enum("text", "image")` — constrains a property to specific values

### Nested Objects

SchemaScript uses inline nested blocks for structured data:

```
avatar_image?: {
  data: Image?
}
```

### Arrays

Append `[]` to a type for collections:

```
colors: int[]
```

For related models:

```
last_messages: {
  data: Message[]
}
```

### Union Types

Properties can accept multiple types using `|`:

```
data: Image|User
```

### Type Precedence

Type modifiers bind in this order (tightest first):

1. `[]` (array) — `int[]` means "array of int"
2. `?` (nullable) — `int[]?` means "nullable array of int"
3. `|` (union) — `string|int` means "string or int"

This means `int[]?` is a nullable array, and `string|int?` is a union of `string` and `nullable int`.

Parentheses override the default precedence:

- `(int?)[]` — array of nullable int
- `(string|int)?` — nullable union
- `((int?)[])[]` — array of arrays of nullable int

## Namespaces and Constants

Namespaces group related constants using `ns` blocks:

```
ns MappingType {
  const camelCase
  const snake_case
}
```

Constants are enumeration-style symbols — they have no assigned value. When referenced, they resolve to their qualified name as a string (e.g. `MappingType::camelCase` resolves to `"MappingType::camelCase"`).

Constants are referenced using `::` syntax:

```
[map:local] = MappingType::camelCase
```

## Comments

Standard `//` line comments:

```
// The users ID
id: int
```

## Key Differences from SAMG

| SAMG | SchemaScript |
|---|---|
| `@name type` | `name: type` |
| `@api_name: localName type` | `@local(localName)` annotation + `[map:local]` |
| `@name type?` | `name: type?` |
| `@name[path] type` | Nested `name: { path: type }` blocks |
| `1:1 Model` / `1:n Model` | `Model` / `Model[]` |
| `1:1? Model` / `1:n? Model` | Optional key (`name?:`) + nullable type (`Model?`) |
| Flat Markdown file | Dedicated `.scsc` file with block structure |
| No custom types | `[types]` block for extending type system |
| No value constraints | `@enum(...)` annotations |
| No union types | `Type1\|Type2` syntax |
| No constants/namespaces | `ns` blocks with `const` values, referenced via `::` |
