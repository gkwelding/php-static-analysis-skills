---
title: Writing Precise Types
tags: phpstan, psalm, phpdoc, generics, templates, list, array-shapes, conditional-types
---

## Writing Precise Types

Native types first: they are enforced at runtime and read by every tool. Add PHPDoc only for what native types can't say: what's inside an array or collection, generics, narrower scalars, and type relationships. Both PHPStan and Psalm read the plain `@param`, `@return`, `@var` and `@template` tags below.

### 1. Arrays: Say What's Inside

`missingType.iterableValue` (level 6) means an `array` type has no value type. The fix is the real element type, not `array<mixed>`.

**Incorrect:**

```php
/** @return array<mixed> */
public function all(): array
{
    return array_values($this->users);
}
```

**Correct:**

```php
/** @return list<User> */
public function all(): array
{
    return array_values($this->users);
}
```

| Type | Use for |
|---|---|
| `list<User>` | Keys `0..n-1` in order: `array_values()`, `[]` appends, `array_map` over a list |
| `array<int, User>` | Integer keys that may have gaps: keyed by ID, after `array_filter()` |
| `array<string, int>` | Maps |
| `array{id: int, name: string, email?: string}` | Fixed shape; `?` marks an optional key |
| `non-empty-list<User>`, `non-empty-array<...>` | Callers rely on at least one element |

Pick what the code produces, not what reads nicest: returning an `array<int, User>` with gaps as `list<User>` is a lie the analyser may not catch.

### 2. Narrower Scalars

| Type | Means |
|---|---|
| `non-empty-string` | `string` that is never `''` |
| `positive-int`, `int<1, max>`, `int<0, 100>` | Integer ranges |
| `class-string<Foo>` | A class name string for `Foo` or a subclass |
| `literal-string` | A string written in code, not built from input |

Only declare what the code guarantees. PHPStan treats PHPDoc types as certain, so `@param non-empty-string $s` makes a later `if ($s === '')` an `identical.alwaysFalse` error (Psalm: `TypeDoesNotContainType`). Either the check is dead (remove it) or the PHPDoc is wrong (fix it). Turning off `treatPhpDocTypesAsCertain` hides both.

### 3. Shapes Used in Several Places

Name the shape once instead of repeating it:

```php
/**
 * @phpstan-type Address array{street: string, postcode: string, country?: string}
 */
final class AddressBook
{
    /** @param Address $address */
    public function add(array $address): void { /* ... */ }
}

/**
 * @phpstan-import-type Address from AddressBook
 */
final class LabelPrinter
{
    /** @param Address $address */
    public function line(array $address): string
    {
        return $address['street'] . ', ' . $address['postcode'];
    }
}
```

Psalm has `@psalm-type` / `@psalm-import-type` for the same thing. If a shape travels far, a small readonly class is usually better than an alias.

### 4. Generics: `@template`

When the return type depends on an argument, declare the relationship instead of returning `object` or `mixed`:

```php
/**
 * @template T of object
 * @param class-string<T> $class
 * @return T
 */
public function make(string $class): object
{
    return new $class();
}
// $container->make(Mailer::class) is now Mailer
```

Generic classes (`Collection<TKey, TValue>`, Eloquent relations, Doctrine repositories) need their type arguments wherever they appear in a signature, or PHPStan reports `missingType.generics` at level 6:

```php
/** @return Collection<int, Invoice> */
public function overdue(): Collection
```

Implementing or extending a generic type: `@implements Repository<Invoice>`, `@extends ServiceEntityRepository<Invoice>`.

### 5. Conditional Return Types

When a flag argument decides the return type:

```php
/**
 * @return ($asList is true ? list<User> : array<int, User>)
 */
public function users(bool $asList): array
```

`users(true)` is then `list<User>` and `users(false)` is `array<int, User>`.

### 6. Assertion Methods

A guard method that throws on bad input can tell the analyser what it proved:

```php
/** @phpstan-assert non-empty-string $value */
public static function nonEmptyString(mixed $value): void
{
    if (!is_string($value) || $value === '') {
        throw new InvalidArgumentException('Expected a non-empty string');
    }
}

/** @phpstan-assert-if-true int $value */
public static function isId(mixed $value): bool
{
    return is_int($value) && $value > 0;
}
```

The PHPDoc must match the body exactly. An assertion the body doesn't enforce is a `@var` lie with extra steps. Psalm's tags are `@psalm-assert` / `@psalm-assert-if-true`.

### Checklist

- [ ] Native type wherever PHP can express it
- [ ] Array and collection types name their key and value types
- [ ] `list<>` only for values that really are lists
- [ ] Narrow scalars and assertions only where the code enforces them
- [ ] Generic classes carry their type arguments in every signature
