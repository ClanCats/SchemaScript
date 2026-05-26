# Build System

The `scsc build` command orchestrates code generation from a `SCHEMA.scsc` file. It compiles the schema, validates it, and runs all configured generators.

## The `SCHEMA.scsc` File

By convention, SchemaScript projects use a file called `SCHEMA.scsc` in the project root. This file combines the schema definitions with generator configuration.

## Structure

A typical `SCHEMA.scsc` follows this structure:

```scsc
import scsc/base

[version] = 1

[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
}

[generate] = {
  [ts.types] = {
    output = 'output/ts/types/'
    include_comments = true
    map = api
  }
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
    map_from = self
    map_to = api
  }
  [php.samg] = {
    output = 'output/php/SAMG/'
    namespace = 'App\\SAMG\\'
    map_from = self
    map_to = api
  }
}

// Type aliases, constants, and model definitions follow...
```

## The `[generate]` Block

The `[generate]` metadata block configures which generators to run and their options. Each entry is a metadata object keyed by the generator name:

```scsc
[generate] = {
  [<generator-name>] = {
    output = '<output-directory>'
    // ... generator-specific options
  }
}
```

### Required Options

| Option | Description |
|--------|-------------|
| `output` | Directory where generated files are written (relative to the schema file) |

### Common Options

| Option | Description | Used by |
|--------|-------------|---------|
| `namespace` | PHP namespace prefix for generated classes | `php.mappers`, `php.samg` |
| `map_from` | Source mapping context (default: `self`) | `php.mappers`, `php.samg` |
| `map_to` | Target mapping context | `php.mappers`, `php.samg` |
| `map` | Which mapping context to apply for property keys | `ts.types` |
| `include_comments` | Include doc comments in output | `ts.types`, `php.mappers` |

See individual generator pages for the full list of options:
- [TypeScript Types](./generators/ts-types.md)
- [PHP Mappers](./generators/php-mappers.md)
- [PHP SAMG](./generators/php-samg.md)

## Available Generators

| Name (SCHEMA.scsc) | Name (CLI) | Description |
|--------------------|------------|-------------|
| `ts.types` | `ts-types` | TypeScript interface definitions |
| `php.mappers` | `php-mappers` | PHP mapper classes with `fromArray`/`toArray` |
| `php.samg` | `php.samg` | PHP SAMG v2 mapper classes with 10 methods |

## Build Process

When you run `scsc build`, the following happens:

1. The `SCHEMA.scsc` file is compiled (lexed, parsed, evaluated) with import resolution
2. The resulting `Definition` is validated
3. Each entry in the `[generate]` block is processed:
   - The generator is looked up by name
   - The generator receives the `Definition` and its configured options
   - Generated files are written to the specified output directory
4. A summary of written files is printed

## Full Example

Here is the integration test `SCHEMA.scsc` as a complete reference:

```scsc
import scsc/base

[version] = 1

[map] = {
  api = {
    strategy = MappingStrategy::snakeCase
  }
  db = {
    strategy = MappingStrategy::snakeCase
  }
}

[generate] = {
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'IntegrationEx\\Mappers\\'
    map_from = self
    map_to = api
  }
  [php.samg] = {
    output = 'output/php/SAMG/'
    namespace = 'IntegrationEx\\SAMG\\'
    map_from = self
    map_to = api
  }
  [ts.types] = {
    output = 'output/ts/types/'
    include_comments = true
    map = api
  }
}

const pk_t = uint64

private PaginationMeta {
  total_count: int
  filtered_count: int
}

private SingleResponse<T> {
  error?: string
  data: T
}

private CollectionResponse<T>: PaginationMeta {
  error?: string
  data: T[]
}

User {
  id: pk_t
  name: string
}

UserResponse: SingleResponse<User> {}
UserCollectionResponse: CollectionResponse<User> {}

[type] = {
  position = { x: int, y: int }
  size = { width: int, height: int }
}

Box {
  // the position of the box
  pos: position
  // the size of the box
  size: size
  // the rotation in radians
  // formula to convert from degrees to radians: degrees * (pi / 180)
  rotation: float
}

[type] = {
  pub MessageType = 'text'|'image'|'video'
}

Message {
  id: pk_t
  type: MessageType
  text: string?
  actor: User?
  unseen?: bool
  payload: map<string, string>
  createdAt: int
  @map.api(modified_at)
  updatedAt: int
}
```
