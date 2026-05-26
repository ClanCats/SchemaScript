# Type Aliases

Type blocks define type aliases and declare custom types using `[type] = { ... }`:

```scsc
[type] = {
  int64
  timestamp = uint64
  uuid = string
}
```

## Bare Type Declarations

A bare identifier (no `=`) registers a type name without aliasing it to another type:

```scsc
[type] = {
  int64
  uint64
}
```

This is primarily used by the standard library to register built-in type names.

## Type Aliases

Use `=` to alias one type to another. Aliases can reference any type expression -- simple types, unions, arrays, nullable types, or inline objects:

```scsc
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

By default, type aliases are **private** -- generators expand them inline wherever they are used. A property typed as `pk_t` will appear as `bigint` in TypeScript and `string` in PHP (based on the resolved `uint64` type), not as a named `pk_t` type.

## Public Type Aliases (`pub`)

Prefix a type alias with `pub` to make it **public**. Public aliases are emitted as standalone, exported types instead of being expanded inline:

```scsc
[type] = {
  pub MessageType = 'text'|'image'|'video'
  pub Position = {
    x: int
    y: int
  }
  internal_status = 'active'|'inactive'   // private, expanded inline
}
```

### TypeScript Output

Public type aliases are collected into a shared `_types.ts` file. Object-shaped types become `export interface`, others become `export type`:

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

### PHP Output

Public object-shaped type aliases generate their own mapper class (e.g., `PositionMapper.php`). Models referencing them delegate to that mapper.

## Type Alias Annotations

Type aliases can have annotations, commonly used for language-specific type overrides:

```scsc
[type] = {
  @lang.php('int')
  @lang.ts('bigint')
  int64

  @lang.php('string')
  @lang.ts('string')
  uuid = string
}
```

The `@lang.php` and `@lang.ts` annotations tell generators what native type to use when emitting code for this type. See [Annotations](./annotations.md) for the full annotation reference.

## Scoped Type Blocks

Type blocks can appear inside models. Types defined in a model scope are available to that model and its children, but not to sibling or parent models:

```scsc
Api {
  [type] = {
    apiId = string
    apiTimestamp = int64
  }

  Request {
    id: apiId
    received_at: apiTimestamp
  }

  Response {
    timestamp: apiTimestamp
  }
}

// apiId and apiTimestamp are NOT available here
User {
  id: int
}
```
