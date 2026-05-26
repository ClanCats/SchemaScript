# Importers

Importers convert external format definitions into SchemaScript `.scsc` files. This lets you adopt SchemaScript incrementally by importing existing schema definitions.

## Usage

```bash
vendor/bin/scsc import <importer> <file> [--output=<file.scsc>] [--stdout]
```

### Options

| Option | Description |
|--------|-------------|
| `--output=<file>` | Write the generated `.scsc` to a file |
| `--stdout` | Print the generated `.scsc` to stdout |

List available importers:

```bash
vendor/bin/scsc --list-import
```

## Available Importers

### SAMG (`samg`)

The SAMG importer reads SAMG (Storage Abstraction Mapping Graph) markdown definition files and converts them into SchemaScript schemas.

```bash
vendor/bin/scsc import samg SAMG.md --output=schema.scsc
```

The importer:
- Parses model definitions and their properties from the SAMG markdown format
- Generates appropriate `[map]` and `[generate]` blocks
- Configures `snake_case` mapping strategy for the API context
- Sets up `php.samg` generator configuration with the namespace and output path from the SAMG metadata

### Piping to a Generator

You can pipe imported output directly to a generator:

```bash
vendor/bin/scsc import samg SAMG.md --stdout | vendor/bin/scsc gen php.samg /dev/stdin --output=output/
```

This is useful for one-off generation without creating an intermediate `.scsc` file.
