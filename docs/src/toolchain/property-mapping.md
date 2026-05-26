# Property Mapping

Property mapping transforms property names between different naming conventions. This is how a `camelCase` property in your schema becomes a `snake_case` key in a REST API.

## Defining Mapping Strategies

The `[map]` metadata block defines named mapping contexts:

```scsc
[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
  db = {
    strategy = MappingStrategy::snakeCase
  }
}
```

Each named context (like `api` or `db`) specifies a naming strategy for property keys.

## Available Strategies

| Strategy | Example Input | Example Output |
|----------|--------------|----------------|
| `MappingStrategy::camelCase` | `total_count` | `totalCount` |
| `MappingStrategy::pascalCase` | `total_count` | `TotalCount` |
| `MappingStrategy::snakeCase` | `totalCount` | `total_count` |
| `MappingStrategy::screamingSnakeCase` | `totalCount` | `TOTAL_COUNT` |
| `MappingStrategy::kebabCase` | `totalCount` | `total-count` |

## Using Mappings in Generators

Generators reference mapping contexts through their configuration options:

### TypeScript Types

The `map` option specifies which mapping context to use for property names in the output:

```scsc
[generate] = {
  [ts.types] = {
    output = 'output/ts/'
    map = api
  }
}
```

With `map = api` and `MappingStrategy::snakeCase`, a property `createdAt` becomes `created_at` in the TypeScript interface.

### PHP Mappers & SAMG

PHP generators use `map_from` and `map_to` to define the source and target contexts:

```scsc
[generate] = {
  [php.mappers] = {
    output = 'output/php/'
    namespace = 'App\\Mappers\\'
    map_from = self
    map_to = api
  }
}
```

- `map_from = self` means the local/input keys use the original property names from the schema
- `map_to = api` means the target/output keys use the `api` mapping strategy

This generates mappers that convert between `camelCase` local keys and `snake_case` API keys:

```php
// fromArray: api -> local
$result['createdAt'] = (int) $data['created_at'];

// toArray: local -> api
$result['created_at'] = (int) $data['createdAt'];
```

## Per-Property Overrides

### `@map.<name>(key)`

Override the mapped key for a specific context:

```scsc
Message {
  createdAt: int

  @map.api(modified_at)
  updatedAt: int
}
```

Without the annotation, `updatedAt` would be automatically converted to `updated_at` by the `snakeCase` strategy. The `@map.api(modified_at)` annotation overrides this, explicitly setting the API key to `modified_at`.

### `@local(name)`

Override the local property name:

```scsc
@local(avatarImageId)
avatar_image_id: int?
```

In PHP mappers, the local key will be `avatarImageId` regardless of the mapping strategy applied.

## Walkthrough Example

Given this schema:

```scsc
import scsc/base

[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}

[generate] = {
  [ts.types] = {
    output = 'output/ts/'
    map = api
  }
  [php.mappers] = {
    output = 'output/php/'
    namespace = 'App\\Mappers\\'
    map_from = self
    map_to = api
  }
}

Message {
  id: uint64
  messageText: string
  createdAt: int
  @map.api(modified_at)
  updatedAt: int
}
```

**TypeScript output** (using `api` map = `snake_case`):

```typescript
export interface Message {
  id: bigint;
  message_text: string;
  created_at: number;
  modified_at: number;    // from @map.api annotation
}
```

**PHP mapper** (converting between `self` and `api`):

```php
public static function fromArray(array $data): array
{
    $result = [];
    $result['id'] = (string) $data['id'];
    $result['messageText'] = (string) $data['message_text'];
    $result['createdAt'] = (int) $data['created_at'];
    $result['updatedAt'] = (int) $data['modified_at'];
    return $result;
}

public static function toArray(array $data): array
{
    $result = [];
    $result['id'] = (string) $data['id'];
    $result['message_text'] = (string) $data['messageText'];
    $result['created_at'] = (int) $data['createdAt'];
    $result['modified_at'] = (int) $data['updatedAt'];
    return $result;
}
```

Notice how:
- `messageText` (camelCase) maps to `message_text` (snake_case) automatically
- `updatedAt` maps to `modified_at` (from the `@map.api` annotation, overriding the automatic `updated_at`)
