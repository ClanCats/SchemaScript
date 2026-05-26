# Inheritance

Models can inherit properties from other models using colon syntax.

## Basic Inheritance

Use `:` after the model name to inherit from a parent model:

```scsc
private PaginationMeta {
  total_count: int
  filtered_count: int
}

UserCollectionResponse: PaginationMeta {
  error?: string
  data: User[]
}
```

The child model (`UserCollectionResponse`) inherits all properties from the parent (`PaginationMeta`). Inherited properties appear before the child's own properties in the generated output:

```typescript
export interface UserCollectionResponse {
  total_count: number;      // inherited from PaginationMeta
  filtered_count: number;   // inherited from PaginationMeta
  error?: string;           // own property
  data: User[];             // own property
}
```

Properties are flattened -- there is no runtime prototype chain or base class in the generated code.

## Inheritance with Generics

Parent models can be generic. Type parameters are resolved at the inheritance site:

```scsc
private SingleResponse<T> {
  error?: string
  data: T
}

private CollectionResponse<T>: PaginationMeta {
  error?: string
  data: T[]
}

UserResponse: SingleResponse<User> {}
UserCollectionResponse: CollectionResponse<User> {}
```

`UserResponse` resolves `T` to `User`, producing:

```typescript
export interface UserResponse {
  error?: string;
  data: User;
}
```

`CollectionResponse<T>` itself inherits from `PaginationMeta`, so `UserCollectionResponse` gets properties from both:

```typescript
export interface UserCollectionResponse {
  total_count: number;       // from PaginationMeta (via CollectionResponse)
  filtered_count: number;    // from PaginationMeta (via CollectionResponse)
  error?: string;            // from CollectionResponse
  data: User[];              // from CollectionResponse, T resolved to User
}
```

## Private Parents

Parent models are often marked `private` since they serve as templates and shouldn't produce their own output files. Only the concrete child models are emitted by generators:

```scsc
private BaseEntity {
  id: uint64
  created_at: timestamp
  updated_at: timestamp
}

User: BaseEntity {
  name: string
  email: string
}

Post: BaseEntity {
  title: string
  body: string
}
```

Both `User` and `Post` will include `id`, `created_at`, and `updated_at` in their generated output. `BaseEntity` itself is not emitted.

## Empty Child Models

A child model with no additional properties is valid -- it simply takes all properties from its parent:

```scsc
UserResponse: SingleResponse<User> {}
```

This is a common pattern for creating concrete types from generic templates.
