# Types

SchemaScript has a rich type system with simple types, arrays, nullable types, unions, inline objects, string literals, and generics.

## Simple Types

A simple type is an identifier referencing a built-in type, a type alias, or a model name:

```scsc
id: int
name: string
author: User
status: MessageType
```

## String Literal Types

Quoted strings can be used as types, typically combined with unions to constrain a property to specific values:

```scsc
type: 'text'|'image'|'video'
status: 'active'|'inactive'|'pending'
```

## Arrays

Append `[]` to a type to make it an array:

```scsc
colors: int[]
tags: string[]
messages: Message[]
```

## Nullable Types

Append `?` to a type to make the value nullable:

```scsc
name: string?
avatar_url: string?
items: int[]?
```

`string?` means the value can be a `string` or `null`. `int[]?` means the value can be an array of ints or `null`.

## Union Types

Combine types with `|`:

```scsc
data: Image|User
result: string|int
type: 'text'|'image'|'video'
```

## Inline Objects

Curly braces define anonymous object types with their own properties:

```scsc
avatar_image: {
  url: string
  width: int
  height: int
}

last_messages: {
  cached: bool
  data: Message[]
}
```

Inline objects are expanded inline in generated code -- they don't produce standalone types.

## Generic Types

Angle brackets provide type arguments to generic models:

```scsc
pages: Paginated<User>
config: map<string, string>
nested: map<string, map<string, int>>
```

See [Generics](./generics.md) for details on defining and using generic models.

## Type Precedence

Type modifiers bind in this order (tightest first):

1. `[]` (array)
2. `?` (nullable)
3. `|` (union)

This means:

| Expression | Meaning |
|-----------|---------|
| `int[]` | Array of int |
| `int[]?` | Nullable array of int |
| `string\|int` | String or int |
| `string\|int?` | String, or nullable int |

### Parentheses

Parentheses override the default precedence:

```scsc
// Array of nullable int
items: (int?)[]

// Nullable union
value: (string|int)?

// Array of arrays of nullable int
matrix: ((int?)[])[]
```

Without parentheses, `int?[]` would mean "nullable array of int". With parentheses, `(int?)[]` means "array of nullable int".
