# Models

Models are the primary building blocks of a SchemaScript schema. They define named data structures with typed properties.

## Basic Syntax

A model is a named block containing property definitions:

```scsc
User {
  id: int
  name: string
  email: string?
}
```

Each model produces one output file per generator (e.g., `User.ts` for TypeScript, `UserMapper.php` for PHP mappers).

## Model-Level Metadata

Models can contain metadata directives that configure behavior specific to that model:

```scsc
User {
  [version] = 2
  [map:local] = 'camelCase'

  id: int
  name: string
}
```

## Child Models

Models can be nested inside other models:

```scsc
User {
  id: int
  name: string

  Profile {
    bio: string
    avatar: string?
  }

  Settings {
    theme: string
    notifications: bool
  }
}
```

Child models are full models in their own right -- they generate their own output files and can be referenced as types by other models.

## Private Models

The `private` keyword prevents a model from being emitted by generators. Private models are useful as base types for inheritance or as generic templates:

```scsc
private PaginationMeta {
  total_count: int
  filtered_count: int
}

private SingleResponse<T> {
  error?: string
  data: T
}

// Public models that inherit from private ones
UserResponse: SingleResponse<User> {}

UserCollectionResponse: PaginationMeta {
  error?: string
  data: User[]
}
```

In this example:
- `PaginationMeta` and `SingleResponse` are not emitted as standalone types
- `UserResponse` inherits `SingleResponse`'s properties (with `T` resolved to `User`) and is emitted
- `UserCollectionResponse` inherits `PaginationMeta`'s properties and is emitted

Generated TypeScript for `UserCollectionResponse`:

```typescript
import type { User } from './User';

export interface UserCollectionResponse {
  total_count: number;
  filtered_count: number;
  error?: string;
  data: User[];
}
```

The inherited properties from `PaginationMeta` are flattened directly into the output.

## See Also

- [Properties](./properties.md) for property syntax and modifiers
- [Generics](./generics.md) for generic type parameters on models
- [Inheritance](./inheritance.md) for extending models from parent types
