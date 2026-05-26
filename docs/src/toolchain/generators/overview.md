# Code Generators

Generators take the compiled `Definition` from a `.scsc` file and produce language-specific output files. SchemaScript ships with three built-in generators.

## Available Generators

| Generator | Output | Description |
|-----------|--------|-------------|
| [TypeScript Types](./ts-types.md) | `.ts` files | TypeScript interfaces and type aliases |
| [PHP Mappers](./php-mappers.md) | `.php` files | PHP classes with `fromArray`/`toArray` methods |
| [PHP SAMG](./php-samg.md) | `.php` files | PHP classes with 10 bidirectional mapping methods |

## Common Behavior

All generators share these behaviors:

### Private Models

Models marked `private` are not emitted as standalone output files. Their properties are available through inheritance -- when a public model inherits from a private one, the inherited properties are flattened into the child's output.

### Generic Template Models

Generic models with unresolved type parameters (e.g., `Paginated<T>`) are skipped by generators. Only concrete instantiations (via inheritance like `UserList: Paginated<User> {}`) produce output.

The exception is TypeScript, which can emit generic interfaces directly (e.g., `export interface Paginated<T> { ... }`) for models without `@lang.ts` annotations.

### Public Type Aliases

Type aliases marked with `pub` produce standalone output:
- **TypeScript:** Collected into a shared `_types.ts` file
- **PHP:** Object-shaped public aliases produce their own mapper class

Private type aliases (the default) are expanded inline wherever they are used.

### Language Annotations

Generic models with `@lang.ts` or `@lang.php` annotations use the annotation value instead of emitting the model. For example, `map<K, V>` with `@lang.ts('Record')` emits `Record<K, V>` in TypeScript instead of generating a `map` interface.

## Configuration

Generators are configured in the `[generate]` metadata block of a `SCHEMA.scsc` file:

```scsc
[generate] = {
  [ts.types] = {
    output = 'output/ts/'
    map = api
    include_comments = true
  }
  [php.mappers] = {
    output = 'output/php/Mappers/'
    namespace = 'App\\Mappers\\'
    map_from = self
    map_to = api
  }
}
```

See [Build System](../build-system.md) for details on the configuration format.

## Running Generators

Generators run automatically with `scsc build`, or individually with `scsc gen`:

```bash
# Run all generators
vendor/bin/scsc build

# Run a specific generator
vendor/bin/scsc gen ts-types schema.scsc --output=output/ts/
vendor/bin/scsc gen php-mappers schema.scsc --output=output/php/
```

List available generators:

```bash
vendor/bin/scsc --list-gen
```
