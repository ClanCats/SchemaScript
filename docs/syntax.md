> **Note:** This document has been superseded by the [mdbook documentation](./src/SUMMARY.md). It is preserved for reference.

# SchemaScript Syntax

SchemaScript (`.scsc`) is a structured, block-based schema definition language. It defines data models with typed properties, annotations, type aliases, namespaces, and constants. A toolchain evaluates `.scsc` files into a `Definition` that code generators consume to produce language-specific output (e.g., TypeScript interfaces, PHP mapper classes).

## Imports

Use `import` to include other `.scsc` files. Path segments are separated by `/`.

```
import scsc/base
import common
```

The standard library provides `scsc/base`, which declares all built-in types (see [Standard Library](#standard-library)).

## Comments

Line comments use `//`. Comments placed directly above a property are attached to that property and preserved through code generation.

```
// The users unique identifier
id: int

// The rotation in radians
// formula: degrees * (pi / 180)
rotation: float
```

Multi-line comments are multiple consecutive `//` lines. Generators emit them as JSDoc in TypeScript and `//` comments in PHP.

## Global Metadata

Top-level metadata uses bracket syntax:

```
[version] = 1
```

Values can be strings, numbers, booleans, identifiers, namespace references, objects, or lists:

```
[version] = 1
[name] = 'MySchema'
[enabled] = true
[mapping] = MappingType::camelCase
```

### Metadata Objects vs Key-Value Objects

There are two different object syntaxes that look similar but produce different data structures:

**Metadata objects** use bracket keys (`[key] = value`). Each entry is a metadata entry — an ordered tuple of key, value, and attributes (annotations). Metadata entries support annotations, dotted key names, and duplicate keys (multiple entries with the same key are preserved as separate entries):

```
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

**Key-value objects** use plain identifiers (`key = value`). These are simple assignments with no annotation support. Duplicate keys override — only the last value is kept:

```
[config] = {
  name = 'example'
  nested = {
    deeper = {
      value = 42
    }
  }
}
```

Both forms can be mixed inside the same object:

```
[generate] = {
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
  }
  debug = false
}
```

### Metadata Lists

Lists use `{value, value}` syntax:

```
[colors] = {'red', 'green', 'blue'}
```

## Generator Configuration

Generator output is configured via the `[generate]` metadata block:

```
[generate] = {
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
  }
  [ts.types] = {
    output = 'output/ts/types/'
    include_comments = true
  }
}
```

## Type Blocks

Type blocks define type aliases and declare custom types using `[type] = { ... }`:

```
[type] = {
  int64
  timestamp = uint64
  uuid = string
}
```

### Bare Type Declarations

A bare identifier (no `=`) registers a type name without an alias:

```
[type] = {
  int64
  uint64
}
```

### Type Aliases

Use `=` to alias one type to another. Aliases can reference any type expression — simple types, unions, arrays, nullable types, or inline objects:

```
[type] = {
  pk_t = uint64
  messageType = 'text'|'image'|'video'
  tags = string[]
  optionalName = string?
  position = {
    x: int
    y: int
  }
}
```

By default, type aliases are **private** — generators expand them inline wherever they are used.

### Public Type Aliases (`pub`)

Prefix a type alias with `pub` to make it **public**. Public aliases are emitted as standalone, exported types instead of being expanded inline:

```
[type] = {
  pub MessageType = 'text'|'image'|'video'
  pub Position = {
    x: int
    y: int
  }
  internal_status = 'active'|'inactive'   // private, expanded inline
}
```

**TypeScript output:** Public type aliases are collected into a shared `_types.ts` file. Object-shaped types become `export interface`, others become `export type`:

```typescript
// _types.ts
export type MessageType = 'text' | 'image' | 'video';

export interface Position {
  x: number;
  y: number;
}
```

Model files that reference public types automatically import them:

```typescript
// Message.ts
import type { MessageType } from './_types';

export interface Message {
  type: MessageType;
}
```

**PHP output:** Public object-shaped type aliases generate their own mapper class (e.g., `PositionMapper.php`). Models referencing them delegate to that mapper.

### Type Alias Annotations

Type aliases can have annotations, commonly used for language-specific type overrides:

```
[type] = {
  @lang.php('int')
  int64
}
```

### Scoped Type Blocks

Type blocks can appear inside models. Types defined in a model scope are available to that model and its children:

```
Api {
  [type] = {
    apiId = string
    apiTimestamp = int64
  }

  Request {
    id: apiId
    received_at: apiTimestamp
  }
}
```

## Constants

Constants are enumeration-style symbols declared with `const`.

### Root-Level Constants

Constants can be declared at the root scope:

```
const pk_t = uint64
const defaultMapping = MappingType::camelCase
```

Root-level constants with a type alias value (like `pk_t = uint64`) can be used as types in property definitions:

```
const pk_t = uint64

User {
  id: pk_t
}
```

### Namespace Constants

Constants grouped inside `ns` blocks form a namespace:

```
ns MappingType {
  const camelCase
  const snake_case
}
```

Constants without assigned values resolve to their qualified name as a string (e.g., `MappingType::camelCase` resolves to `"MappingType::camelCase"`).

Constants are referenced using `::` syntax:

```
[map:local] = MappingType::camelCase
```

## Model Definitions

Models are defined as named blocks:

```
User {
  id: int
  name: string
}
```

### Model-Level Metadata

Models can contain metadata directives:

```
User {
  [version] = 2
  [map:local] = 'camelCase'

  id: int
  name: string
}
```

- `[version]` — model schema version
- `[map:local]` — local name mapping strategy (e.g., `'camelCase'` converts `snake_case` names to camelCase)

### Child Models

Models can be nested inside other models:

```
User {
  id: int

  Profile {
    bio: string
    avatar: string?
  }
}
```

## Generics

Models can declare type parameters in angle brackets after the model name:

```
Paginated<T> {
    items: T[]
    total: int
}

Result<T, E> {
    data: T?
    error: E?
}
```

Type parameters (`T`, `E`, etc.) can be used anywhere a type is expected inside the model body — in arrays, nullable types, unions, and inline objects.

### Generic Type Instantiation

Use a generic model by providing concrete type arguments:

```
UserList {
    pages: Paginated<User>
    outcome: Result<User, string>
}
```

The number of type arguments must match the number of type parameters declared on the model. Type parameter names must be unique within a model declaration.

### Combining with Other Type Modifiers

Generic types follow the same precedence rules as other types and can be combined with arrays, nullable, and unions:

```
Config {
    items: map<string, int>[]
    cache: map<string, User>?
    nested: map<string, map<string, int>>
}
```

### Child Model Scope

Child models nested inside a generic model can reference the parent's type parameters:

```
Container<T> {
    value: T

    Metadata {
        item: T
        label: string
    }
}
```

### Standard Library Generic: `map`

The standard library provides `map<K, V>`, a key-value mapping type. It generates language-appropriate output:

- **TypeScript:** `Record<K, V>`
- **PHP:** `array` with value casting via `array_map()`

```
Settings {
    config: map<string, string>
    scores: map<string, int>
}
```

### Custom Generic Models with Language Annotations

To control how a custom generic model is emitted by generators, use `@lang.php` and `@lang.ts` annotations. Generic models with these annotations are not emitted as standalone types — instead, generators use the annotation value at each usage site:

```
@lang.php('array')
@lang.ts('Record')
map<K, V> {}
```

Generic models without language annotations are emitted as proper generic types in languages that support them (e.g., TypeScript emits `export interface Paginated<T> { ... }`). PHP generators skip generic template models entirely and only handle concrete instantiations.

## Properties

Properties use `name: type` syntax:

```
id: int
name: string
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

Here `avatar_image?` means the key itself may be absent, while `Image?` means when present, the value can be null.

### Property Annotations

Annotations add metadata to properties:

```
@local(avatarImageId)
avatar_image_id: int?

@enum("text", "image")
type: string
```

## Type System

### Simple Types

Type names referencing built-in types, type aliases, or model names:

```
id: int
name: string
author: User
```

### String Literal Types

Quoted strings can be used as types, typically in unions to constrain values:

```
type: 'text'|'image'|'video'
```

### Arrays

Append `[]` to a type for collections:

```
colors: int[]
tags: string[]
messages: Message[]
```

### Nullable

Append `?` to make a type nullable:

```
name: string?
items: int[]?
```

### Union Types

Combine types with `|`:

```
data: Image|User
type: 'text'|'image'|'video'
```

### Inline Objects

Curly braces define anonymous object types:

```
avatar_image?: {
  data: Image?
}

last_messages: {
  data: Message[]
}
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

## Standard Library

The standard library (`scsc/base`) provides these built-in types:

| Category | Types |
|---|---|
| Integers | `int`, `int8`, `int16`, `int32`, `int64` |
| Unsigned integers | `uint`, `uint8`, `uint16`, `uint32`, `uint64` |
| Floating point | `float`, `float32`, `float64`, `double` |
| Text | `string` |
| Boolean | `bool` |
| Binary | `bytes` |
| Date/Time | `timestamp`, `datetime`, `date`, `time` |
| Identifiers | `uuid` |
| Dynamic | `any`, `mixed` |
| Collections | `map<K, V>` |

Import with:

```
import scsc/base
```

## Annotations

Annotations use `@name` or `@name(args)` syntax. Names can be dotted for namespacing:

```
@local(avatarImageId)
@enum("text", "image", "video")
@lang.php('int')
@lang.ts('number')
```

Arguments are comma-separated and can be strings, numbers, or identifiers.

### Known Annotations

- `@local(name)` — explicit local name override for a property
- `@enum(values...)` — constrains a property to specific string values
- `@lang.php(type)` — override the PHP type for a type alias
- `@lang.ts(type)` — override the TypeScript type for a type alias
