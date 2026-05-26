# Generics

SchemaScript supports generic type parameters on models and generic type instantiation in property types.

## Declaring Generic Models

Models can declare type parameters in angle brackets after the model name:

```scsc
Paginated<T> {
  items: T[]
  total: int
}

Result<T, E> {
  data: T?
  error: E?
}
```

Type parameters (`T`, `E`, etc.) can be used anywhere a type is expected inside the model body -- in arrays, nullable types, unions, and inline objects.

## Generic Type Instantiation

Use a generic model by providing concrete type arguments in angle brackets:

```scsc
UserList {
  pages: Paginated<User>
  outcome: Result<User, string>
}
```

The number of type arguments must match the number of type parameters declared on the model. Type parameter names must be unique within a model declaration.

## Combining with Other Type Modifiers

Generic types follow the same precedence rules as other types and can be combined with arrays, nullable, and unions:

```scsc
Config {
  items: map<string, int>[]
  cache: map<string, User>?
  nested: map<string, map<string, int>>
}
```

## Child Model Scope

Child models nested inside a generic model can reference the parent's type parameters:

```scsc
Container<T> {
  value: T

  Metadata {
    item: T
    label: string
  }
}
```

## Generic Models and Inheritance

Generic models can be used as parent types. The type parameters are resolved at the inheritance site:

```scsc
private SingleResponse<T> {
  error?: string
  data: T
}

private CollectionResponse<T> {
  error?: string
  data: T[]
}

UserResponse: SingleResponse<User> {}
UserCollectionResponse: CollectionResponse<User> {}
```

`UserResponse` inherits `SingleResponse`'s properties with `T` resolved to `User`:

```typescript
// UserResponse.ts
import type { User } from './User';

export interface UserResponse {
  error?: string;
  data: User;
}
```

`UserCollectionResponse` similarly resolves `T` to `User`:

```typescript
// UserCollectionResponse.ts
import type { User } from './User';

export interface UserCollectionResponse {
  error?: string;
  data: User[];
}
```

## Standard Library Generic: `map`

The standard library provides `map<K, V>`, a key-value mapping type. It generates language-appropriate output:

- **TypeScript:** `Record<K, V>`
- **PHP:** `array` with value casting via `array_map()`

```scsc
Settings {
  config: map<string, string>
  scores: map<string, int>
}
```

Generated TypeScript:

```typescript
export interface Settings {
  config: Record<string, string>;
  scores: Record<string, number>;
}
```

Generated PHP mapper (excerpt):

```php
$result['config'] = array_map(fn($v) => (string) $v, $data['config']);
$result['scores'] = array_map(fn($v) => (int) $v, $data['scores']);
```

## Custom Generic Models with Language Annotations

To control how a custom generic model is emitted by generators, use `@lang.php` and `@lang.ts` annotations. Generic models with these annotations are not emitted as standalone types -- instead, generators use the annotation value at each usage site:

```scsc
@lang.php('array')
@lang.ts('Record')
map<K, V> {}
```

Generic models **without** language annotations are emitted as proper generic types in languages that support them. For example, TypeScript emits `export interface Paginated<T> { ... }`. PHP generators skip generic template models entirely and only handle concrete instantiations through inheritance.

## How Generators Handle Generics

| Scenario | TypeScript | PHP |
|----------|-----------|-----|
| Generic template (`Paginated<T>`) | Emits `interface Paginated<T>` | Skips (not emitted) |
| Generic with `@lang.ts`/`@lang.php` | Uses annotation value (e.g., `Record<K, V>`) | Uses annotation value |
| Concrete instantiation via inheritance | Resolves type parameters, emits flat interface | Resolves type parameters, emits mapper |
| `private` generic template | Not emitted | Not emitted |
