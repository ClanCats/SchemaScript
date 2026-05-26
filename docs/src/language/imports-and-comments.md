# Imports & Comments

## Imports

Use `import` to include other `.scsc` files. Path segments are separated by `/`:

```scsc
import scsc/base
import common
import models/user
```

The standard library provides `scsc/base`, which declares all built-in types (see [Standard Library](./stdlib.md)). Most schemas begin with `import scsc/base`.

Import paths are resolved relative to registered namespace directories. The SchemaScript toolchain resolves imports by scanning registered directories for `.scsc` files and mapping their file paths to import names. For example, a file at `schemas/api/user.scsc` registered under the prefix `api` would be imported as `import api/user`.

Circular imports are detected and produce an error.

## Comments

Line comments use `//`:

```scsc
// This is a comment
User {
  id: int
}
```

### Multi-Line Comments

Multiple consecutive `//` lines form a multi-line comment:

```scsc
// The rotation angle in radians.
// Formula to convert from degrees:
// degrees * (pi / 180)
rotation: float
```

### Comments on Properties

Comments placed directly above a property are attached to that property and preserved through code generation. In TypeScript, they become JSDoc comments. In PHP, they become `//` comments.

```scsc
User {
  // The user's unique identifier
  id: int

  // The user's display name
  name: string
}
```

**Generated TypeScript** (with `include_comments = true`):

```typescript
export interface User {
  /** The user's unique identifier */
  id: number;
  /** The user's display name */
  name: string;
}
```

Multi-line comments above a property are preserved as multi-line JSDoc:

```scsc
Box {
  // the rotation in radians
  // formula to convert from degrees to radians: degrees * (pi / 180)
  rotation: float
}
```

```typescript
export interface Box {
  /**
   * the rotation in radians
   * formula to convert from degrees to radians: degrees * (pi / 180)
   */
  rotation: number;
}
```
