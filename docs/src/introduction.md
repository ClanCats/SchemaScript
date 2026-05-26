# Introduction

SchemaScript (`.scsc`) is a structured, block-based schema definition language. You define your data models once -- with typed properties, annotations, generics, inheritance, and mapping rules -- and a PHP toolchain compiles them into language-specific output: TypeScript interfaces, PHP mapper classes, and more.

## Why SchemaScript?

Most projects that communicate across language boundaries end up maintaining parallel type definitions: TypeScript interfaces for the frontend, PHP arrays or DTOs for the backend, maybe a database migration layer on top. These inevitably drift apart. A renamed field in one place becomes a silent bug in another.

SchemaScript eliminates this by making the schema the single source of truth. You describe your data structures in `.scsc` files, and the toolchain generates type-safe code for each target language -- with automatic property name mapping, type casting, and null safety built in.

**One schema, many outputs:**

```
┌─────────────┐
│  .scsc file  │
└──────┬──────┘
       │
       ▼
┌─────────────┐     ┌────────────────────┐
│   Compiler   │────▶│  TypeScript types   │  User.ts, _types.ts
└──────┬──────┘     └────────────────────┘
       │            ┌────────────────────┐
       ├───────────▶│   PHP Mappers       │  UserMapper.php
       │            └────────────────────┘
       │            ┌────────────────────┐
       └───────────▶│   PHP SAMG Maps     │  UserMap.php
                    └────────────────────┘
```

## Key Features

- **Typed properties** with integers, floats, strings, booleans, timestamps, UUIDs, and more
- **Generics** for reusable model templates (`Paginated<T>`, `Result<T, E>`)
- **Inheritance** to compose models from shared base structures
- **Property mapping** with automatic name conversion between `camelCase`, `snake_case`, and other strategies
- **Type aliases** (public and private) for domain-specific types and string literal unions
- **Annotations** for language-specific overrides and custom metadata
- **Multiple generators** producing TypeScript interfaces, PHP mapper classes, and PHP SAMG maps from a single schema

## Pipeline

The compilation pipeline transforms `.scsc` source through several stages:

```
Source  ──▶  Lexer  ──▶  Parser  ──▶  AST  ──▶  Evaluator  ──▶  Definition  ──▶  Generator
 .scsc       tokens      nodes       tree       resolved         output files
```

1. The **Lexer** tokenizes source text into a stream of typed tokens
2. The **Parser** builds an abstract syntax tree (AST) from the token stream
3. The **Evaluator** walks the AST, resolves types, imports, and aliases, and produces a `Definition`
4. **Generators** consume the `Definition` to emit language-specific code

You don't need to understand the pipeline internals to use SchemaScript -- this is just to give you a mental model of how `.scsc` files become generated code.

## What's Next

- [Getting Started](./getting-started.md) walks you through installation and your first schema
- The [Language](./language/syntax-overview.md) section is a complete reference for the `.scsc` syntax
- The [Toolchain](./toolchain/cli.md) section covers the CLI, build system, and generators
