---
title: Common Errors and Their Real Fixes
tags: phpstan, psalm, identifiers, nullability, mixed, missing-types
---

## Common Errors and Their Real Fixes

The errors that make up most runs, with the PHPStan identifier, the Psalm issue type, and the fix. For anything not listed, read `https://phpstan.org/error-identifiers/<identifier>` or `vendor/vimeo/psalm/docs/running_psalm/issues/<Type>.md` before changing code.

### 1. Missing Types

| PHPStan (level 6) | Psalm (level 2 and stricter) |
|---|---|
| `missingType.return`, `missingType.parameter`, `missingType.property` | `MissingReturnType`, `MissingParamType`, `MissingPropertyType` |
| `missingType.iterableValue`, `missingType.generics` | (inferred; reported when the inferred type conflicts) |

Read the body and every caller, then write the type the code really uses (`types.md`). Don't write `mixed` because a parameter is passed around without being touched: find where it's created.

**Incorrect:**

```php
public function count($items) { return count($items); }
```

**Correct:**

```php
/** @param list<OrderLine> $items */
public function count(array $items): int { return count($items); }
```

### 2. Possibly Null (PHPStan Level 8)

| PHPStan | Psalm |
|---|---|
| `method.nonObject`: "Cannot call method getName() on App\User\|null." | `PossiblyNullReference` |
| `property.nonObject`: "Cannot access property $email on App\User\|null." | `PossiblyNullPropertyFetch` |
| `argument.type`: "... expects string, string\|null given." | `PossiblyNullArgument` |
| `return.type`: "... should return string but returns string\|null." | `NullableReturnStatement` + `InvalidNullableReturnType` |

Decide what null means at this point, then write that:

**Incorrect:**

```php
public function nameOf(int $id): string
{
    return (string) $this->find($id)?->getName();
}
```

**Correct** (a missing user is an error here):

```php
public function nameOf(int $id): string
{
    $user = $this->find($id) ?? throw new UserNotFound($id);

    return $user->getName() ?? $user->email;   // only if "fall back to email" is the intended behaviour
}
```

If null is a legitimate result, make the return type nullable and fix every caller the next run reports. Don't stop at the signature.

### 3. Mixed (PHPStan Levels 9-10, Psalm Level 1)

| PHPStan | Psalm |
|---|---|
| `argument.type`: "... expects array\|Countable, mixed given." | `MixedArgument` |
| `offsetAccess.nonOffsetAccessible`: "Cannot access offset 'name' on mixed." | `MixedArrayAccess` |
| `return.type`: "... should return string but returns mixed." | `MixedReturnStatement` |
| | `MixedAssignment` |

Level 9 reports explicit `mixed`; level 10 also reports implicit mixed (untyped parameters and properties). `mixed` comes from a boundary: `json_decode()`, request input, config, cache, `unserialize()`, an untyped library. Narrow it there, once, and throw on bad data.

**Incorrect:**

```php
$data = json_decode($json, true);
return $data['name'];
```

**Correct:**

```php
$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
if (!is_array($data) || !isset($data['name']) || !is_string($data['name'])) {
    throw new UnexpectedValueException('Payload has no name');
}

return $data['name'];
```

For structured payloads, map into a typed DTO in one place rather than narrowing field by field throughout the code. Laravel config and request input: `laravel-larastan.md`.

### 4. Union Types (PHPStan Level 7)

Level 7 adds `reportMaybes`: `int|string` passed where `int` is expected is now an `argument.type` error (Psalm: `InvalidScalarArgument` / `PossiblyInvalidArgument`). Narrow with a real check (`is_int()`, `instanceof`, `match`), or fix the producer so it returns one type.

### 5. Always-True / Always-False (PHPStan Level 4)

`identical.alwaysFalse`, `notIdentical.alwaysTrue`, `booleanAnd.alwaysFalse` and friends mean a check can't change the outcome given the declared types. Either the check is dead code (remove it) or the declared type is wrong (fix the type). Psalm: `TypeDoesNotContainType`, `RedundantCondition`.

### 6. PHPDoc Contradicts Native Type

`varTag.nativeType`, `return.phpDocType`, `parameter.phpDocType`: the PHPDoc says something the native type rules out. The native type wins at runtime, so fix the PHPDoc.
