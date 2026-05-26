# TypeScript Types Generator

The `ts.types` generator produces TypeScript interface definitions and type aliases from SchemaScript models.

## Configuration

```scsc
[generate] = {
  [ts.types] = {
    output = 'output/ts/types/'
    map = api
    include_comments = true
  }
}
```

### Options

| Option | Required | Description |
|--------|----------|-------------|
| `output` | Yes | Output directory for generated `.ts` files |
| `map` | No | Mapping context to apply for property names |
| `include_comments` | No | Include JSDoc comments from schema comments |

**CLI name:** `ts-types`

```bash
vendor/bin/scsc gen ts-types schema.scsc --output=output/ts/
```

## Output Files

The generator produces:

- **One `.ts` file per model** named after the model (e.g., `User.ts`, `Message.ts`)
- **`_types.ts`** if there are public type aliases -- contains all `pub` type aliases

## Examples

### Simple Model

```scsc
User {
  id: uint64
  name: string
}
```

**`User.ts`:**

```typescript
export interface User {
  id: bigint;
  name: string;
}
```

### Model with References and Nullable Types

```scsc
Message {
  id: uint64
  type: MessageType
  text: string?
  actor: User?
  unseen?: bool
  payload: map<string, string>
  created_at: int
  modified_at: int
}
```

**`Message.ts`:**

```typescript
import type { MessageType } from './_types';
import type { User } from './User';

export interface Message {
  id: bigint;
  type: MessageType;
  text: string | null;
  actor: User | null;
  unseen?: boolean;
  payload: Record<string, string>;
  created_at: number;
  modified_at: number;
}
```

### Public Type Aliases

```scsc
[type] = {
  pub MessageType = 'text'|'image'|'video'
}
```

**`_types.ts`:**

```typescript
export type MessageType = 'text' | 'image' | 'video';
```

### Inline Objects with Comments

```scsc
Box {
  // the position of the box
  pos: position
  // the size of the box
  size: size
  // the rotation in radians
  // formula to convert from degrees to radians: degrees * (pi / 180)
  rotation: float
}
```

Where `position = { x: int, y: int }` and `size = { width: int, height: int }` are private type aliases:

**`Box.ts`:**

```typescript
export interface Box {
  /** the position of the box */
  pos: {
    x: number;
    y: number;
  };
  /** the size of the box */
  size: {
    width: number;
    height: number;
  };
  /**
   * the rotation in radians
   * formula to convert from degrees to radians: degrees * (pi / 180)
   */
  rotation: number;
}
```

### Inheritance with Generics

```scsc
private SingleResponse<T> {
  error?: string
  data: T
}

UserResponse: SingleResponse<User> {}
```

**`UserResponse.ts`:**

```typescript
import type { User } from './User';

export interface UserResponse {
  error?: string;
  data: User;
}
```

### Inherited Properties from Multiple Parents

```scsc
private PaginationMeta {
  total_count: int
  filtered_count: int
}

private CollectionResponse<T>: PaginationMeta {
  error?: string
  data: T[]
}

UserCollectionResponse: CollectionResponse<User> {}
```

**`UserCollectionResponse.ts`:**

```typescript
import type { User } from './User';

export interface UserCollectionResponse {
  total_count: number;
  filtered_count: number;
  error?: string;
  data: User[];
}
```

## Type Mapping Reference

| SchemaScript | TypeScript |
|-------------|-----------|
| `int`, `int8`...`int64` | `number` |
| `uint`...`uint32` | `number` |
| `uint64` | `bigint` |
| `float`, `double` | `number` |
| `string` | `string` |
| `bool` | `boolean` |
| `bytes` | `Uint8Array` |
| `uuid`, `timestamp`, `datetime`, `date`, `time` | `string` |
| `any`, `mixed` | `unknown` |
| `T[]` | `T[]` |
| `T?` | `T \| null` |
| `map<K, V>` | `Record<K, V>` |
| `'a'\|'b'\|'c'` | `'a' \| 'b' \| 'c'` |
| `{ x: int }` | `{ x: number }` |
| Model reference | Import + type name |
