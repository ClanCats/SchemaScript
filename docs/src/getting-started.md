# Getting Started

This guide walks you through installing SchemaScript, writing your first schema, and generating code from it.

## Requirements

- PHP >= 8.3
- Composer

## Installation

Install SchemaScript via Composer:

```bash
composer require clancats/schemascript
```

This adds the `scsc` CLI tool at `vendor/bin/scsc`.

## Your First Schema

Create a file called `SCHEMA.scsc` in your project root:

```scsc
import scsc/base

[generate] = {
  [ts.types] = {
    output = 'generated/ts/'
  }
}

User {
  id: int
  name: string
  email: string?
}

Post {
  id: int
  title: string
  body: string
  author: User
  tags: string[]
}
```

This schema defines two models (`User` and `Post`) and configures a single generator that will produce TypeScript type definitions.

## Building

Run the build command from the directory containing your `SCHEMA.scsc`:

```bash
vendor/bin/scsc build
```

This generates the following files:

**`generated/ts/User.ts`:**
```typescript
export interface User {
  id: number;
  name: string;
  email: string | null;
}
```

**`generated/ts/Post.ts`:**
```typescript
import type { User } from './User';

export interface Post {
  id: number;
  title: string;
  body: string;
  author: User;
  tags: string[];
}
```

Notice how SchemaScript automatically:
- Mapped `int` to `number` and `string?` to `string | null`
- Generated an import statement for the `User` reference in `Post`
- Produced a clean `string[]` array type for `tags`

## Adding PHP Mappers

Extend your schema to also generate PHP mapper classes. Update the `[generate]` block:

```scsc
import scsc/base

[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}

[generate] = {
  [ts.types] = {
    output = 'generated/ts/'
    map = api
  }
  [php.mappers] = {
    output = 'generated/php/'
    namespace = 'App\\Mappers\\'
    map_from = self
    map_to = api
  }
}

User {
  id: int
  name: string
  email: string?
}

Post {
  id: int
  title: string
  body: string
  author: User
  tags: string[]
  createdAt: int
}
```

Run `vendor/bin/scsc build` again. Now you get TypeScript types (with `snake_case` property names from the `api` mapping) and PHP mappers that convert between `camelCase` local keys and `snake_case` API keys:

**`generated/php/PostMapper.php`** (excerpt):
```php
class PostMapper
{
    public static function fromArray(array $data): array
    {
        $result = [];
        $result['id'] = (int) $data['id'];
        $result['title'] = (string) $data['title'];
        $result['body'] = (string) $data['body'];
        $result['author'] = UserMapper::fromArray($data['author']);
        $result['tags'] = array_map(fn($v) => (string) $v, $data['tags']);
        $result['createdAt'] = (int) $data['created_at'];
        return $result;
    }
}
```

The mapper automatically:
- Casts each property to its PHP type
- Delegates nested model references to their own mapper
- Converts `created_at` (API/snake_case) to `createdAt` (local/camelCase)

## Inspecting Your Schema

You can inspect the parsed schema without generating code:

```bash
# Print the evaluated Definition as JSON
vendor/bin/scsc SCHEMA.scsc

# Print the raw AST
vendor/bin/scsc ast SCHEMA.scsc
```

## Quick Reference

| Command | Description |
|---------|-------------|
| `scsc build` | Build all generators from `SCHEMA.scsc` |
| `scsc gen <name> <file>` | Run a specific generator |
| `scsc <file>` | Parse and print as JSON |
| `scsc ast <file>` | Print the AST |
| `scsc --list-gen` | List available generators |

## Next Steps

- [Syntax Overview](./language/syntax-overview.md) for a bird's-eye view of the language
- [Types](./language/types.md) to learn about the type system
- [Models](./language/models.md) for model definitions, nesting, and visibility
- [Property Mapping](./toolchain/property-mapping.md) for name transformation strategies
- [Code Generators](./toolchain/generators/overview.md) for detailed generator documentation
