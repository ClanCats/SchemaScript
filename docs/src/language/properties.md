# Properties

Properties define the fields of a model using `name: type` syntax.

## Basic Properties

```scsc
User {
  id: int
  name: string
  email: string
  age: uint8
  balance: float
  active: bool
}
```

## Nullable Types

Append `?` to the type to indicate the value can be null:

```scsc
User {
  id: int
  name: string
  avatar_url: string?    // can be string or null
  bio: string?           // can be string or null
}
```

In generated TypeScript, `string?` becomes `string | null`. In PHP mappers, nullable properties include a null check before type casting.

## Optional Keys

Append `?` to the key name (before the colon) to make the key itself optional. An optional key may or may not be present in the data:

```scsc
Message {
  id: int
  text: string
  unseen?: bool          // key may be absent entirely
}
```

This is distinct from nullable types:

| Syntax | Meaning |
|--------|---------|
| `name: string?` | Key is always present, value can be null |
| `name?: string` | Key may be absent, value is a string when present |
| `name?: string?` | Key may be absent, value can be null when present |

In generated TypeScript, optional keys use the `?` property syntax:

```typescript
export interface Message {
  id: number;
  text: string;
  unseen?: boolean;
}
```

In PHP mappers, optional keys are guarded with `array_key_exists`:

```php
if (array_key_exists('unseen', $data)) {
    $result['unseen'] = (bool) $data['unseen'];
}
```

## Property Annotations

Annotations placed directly above a property add metadata to it:

```scsc
User {
  @local(avatarImageId)
  avatar_image_id: int?

  @enum("text", "image", "video")
  type: string

  @map.api(modified_at)
  updatedAt: int
}
```

See [Annotations](./annotations.md) for the full annotation reference.

## Property Comments

Comments placed directly above a property are attached to it and preserved through code generation:

```scsc
User {
  // The user's unique identifier
  id: int

  // The user's display name, shown in the UI
  name: string
}
```

See [Imports & Comments](./imports-and-comments.md) for details on comment formatting in generated code.
