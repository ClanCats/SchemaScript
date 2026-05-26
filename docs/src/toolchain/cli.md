# CLI Reference

The SchemaScript CLI (`scsc`) is the main entry point for working with `.scsc` files. It can parse, evaluate, build, generate, and import schemas.

## Usage

```bash
vendor/bin/scsc <command> [arguments] [options]
```

## Commands

### `scsc build`

Build all generators configured in the `SCHEMA.scsc` file in the current working directory.

```bash
vendor/bin/scsc build
```

This is the default command when no arguments are provided. It reads the `[generate]` metadata block and runs each configured generator, writing output files to the specified directories.

See [Build System](./build-system.md) for details on the `SCHEMA.scsc` format.

### `scsc <file>`

Parse and evaluate a `.scsc` file, printing the resulting `Definition` as JSON:

```bash
vendor/bin/scsc schema.scsc
```

This is useful for inspecting the fully resolved schema -- all imports resolved, type aliases expanded, and inheritance flattened.

### `scsc ast <file>`

Print the abstract syntax tree (AST) of a `.scsc` file as JSON:

```bash
vendor/bin/scsc ast schema.scsc
```

This shows the raw parse tree before evaluation. Useful for debugging parser behavior or understanding how the language is parsed.

### `scsc gen <generator> <file>`

Run a specific generator on a schema file:

```bash
vendor/bin/scsc gen ts-types schema.scsc --output=generated/ts/
vendor/bin/scsc gen php-mappers schema.scsc --output=generated/php/
vendor/bin/scsc gen php.samg schema.scsc --output=generated/samg/
```

**Options:**

| Option | Description |
|--------|-------------|
| `--output=<dir>` | Output directory for generated files |
| `--stdout` | Print generated code to stdout instead of writing files |

> **Note:** Generator names use hyphens on the CLI (`ts-types`, `php-mappers`) but dots in `SCHEMA.scsc` configuration (`ts.types`, `php.mappers`).

### `scsc import <importer> <file>`

Import from an external format into SchemaScript:

```bash
vendor/bin/scsc import samg SAMG.md --output=schema.scsc
vendor/bin/scsc import samg SAMG.md --stdout
```

**Options:**

| Option | Description |
|--------|-------------|
| `--output=<file>` | Output file path for the generated `.scsc` |
| `--stdout` | Print generated `.scsc` to stdout |

See [Importers](./importers.md) for available importers.

### `scsc --list-gen`

List all available generators:

```bash
vendor/bin/scsc --list-gen
```

### `scsc --list-import`

List all available importers:

```bash
vendor/bin/scsc --list-import
```

## Examples

```bash
# Full build from SCHEMA.scsc
vendor/bin/scsc build

# Inspect the evaluated schema
vendor/bin/scsc SCHEMA.scsc

# Generate only TypeScript types to stdout
vendor/bin/scsc gen ts-types SCHEMA.scsc --stdout

# Import a SAMG file and pipe directly to a generator
vendor/bin/scsc import samg SAMG.md --stdout | vendor/bin/scsc gen php-mappers /dev/stdin --stdout
```
