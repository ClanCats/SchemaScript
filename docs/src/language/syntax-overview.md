# Syntax Overview

SchemaScript files use the `.scsc` extension. The language is block-based and whitespace-insensitive (outside of strings). A schema file contains a mix of top-level directives and model definitions.

## Structure of a `.scsc` File

A typical schema file follows this structure:

```scsc
// 1. Imports
import scsc/base

// 2. Global metadata
[version] = 1

// 3. Mapping configuration
[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}

// 4. Generator configuration
[generate] = {
  [ts.types] = {
    output = 'output/ts/'
  }
}

// 5. Constants
const pk_t = uint64

// 6. Namespaces
ns Visibility {
  const public
  const private
}

// 7. Type aliases
[type] = {
  pub MessageType = 'text'|'image'|'video'
  position = { x: int, y: int }
}

// 8. Model definitions
User {
  id: pk_t
  name: string
  email: string?
  visibility: Visibility::public
}

// 9. Models with generics and inheritance
private SingleResponse<T> {
  error?: string
  data: T
}

UserResponse: SingleResponse<User> {}

Message {
  id: pk_t
  type: MessageType
  text: string?
  author: User?
  tags: string[]
  metadata: map<string, string>

  @map.api(modified_at)
  updatedAt: int
}
```

## Top-Level Constructs

| Construct | Syntax | Purpose |
|-----------|--------|---------|
| Import | `import path/to/file` | Include another `.scsc` file |
| Comment | `// text` | Line comment |
| Metadata | `[key] = value` | Schema-level configuration |
| Namespace | `ns Name { ... }` | Group constants |
| Constant | `const name = value` | Define a constant |
| Type block | `[type] = { ... }` | Define type aliases |
| Model | `Name { ... }` | Define a data model |

## Conventions

- **File extension:** `.scsc`
- **Entry point:** `SCHEMA.scsc` in the project root (used by `scsc build`)
- **Standard library:** `import scsc/base` for built-in types
- **No semicolons:** Statements are newline-separated
- **No commas:** Properties and metadata entries are newline-separated (commas are not used)
