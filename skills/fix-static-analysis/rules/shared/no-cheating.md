---
title: No Cheating
tags: phpstan, psalm, ignore, suppress, baseline, var, assert, cast, mixed
---

## No Cheating

An error is fixed when the code or its types are right, not when the analyser goes quiet. Every shortcut below makes the error disappear and leaves the bug or the wrong type in place.

### 1. Forbidden by Default

| Shortcut | Why it's a cheat |
|---|---|
| New `@phpstan-ignore`, `@phpstan-ignore-line`, `@phpstan-ignore-next-line` | Hides the error; the bug stays |
| New `@psalm-suppress`, new `<issueHandlers>` entry | Same, for Psalm |
| New `ignoreErrors` entry in `phpstan.neon` | Same, project-wide |
| Regenerating the baseline while new errors exist | Absorbs them (`baseline.md`) |
| Widening a type: `string` → `?string`, `User` → `mixed`, `array<int, User>` → `array` | Moves the error to every caller, or turns checks off |
| `/** @var User $user */` over a value that can be something else | Unchecked; see section 3 |
| `assert($x !== null)` over a value that can really be null | No-op in production (`zend.assertions=-1`) |
| `(string) $value`, `(int) $value`, `?? ''`, `?? 0` added only to satisfy a type | Turns a null or wrong type into a plausible wrong value |
| Lowering `level` / raising `errorLevel`, `treatPhpDocTypesAsCertain: false`, `reportUnmatchedIgnoredErrors: false`, `<MissingReturnType errorLevel="suppress" />` | Turns checks off for everyone |
| `ignoreErrors: - identifier: missingType.iterableValue` | The PHPStan 2 replacement for the removed `checkMissingIterableValueType: false`; same cheat |

Config changes that give the analyser *more* information (`containerXmlPath`, `objectManagerLoader`, Larastan's `parseModelCastsMethod`, a stub file for a wrongly typed vendor method) are not cheats, but they are config changes: propose them and say why, don't slip them in.

### 2. Genuine False Positives

An error is a false positive only when the code is correct for every value the types allow and the analyser can't see it: a vendor method with a wrong PHPDoc, a framework magic the extension doesn't model, an analyser bug. "We know it's never null in practice" is not one; make the code say so (section 4).

Before ignoring, try in order: fix the type at its source, a stub file (`stubFiles:` in `phpstan.neon`, `<stubs>` in `psalm.xml`) for a wrongly typed vendor signature, the framework extension's config. Only then ignore, with the narrowest form and a reason.

**Incorrect:**

```php
// @phpstan-ignore-next-line
return $this->client->send($request);
```

**Correct:**

```php
return $this->client->send($request); // @phpstan-ignore return.type (Client::send() PHPDoc says ResponseInterface|null; it never returns null since v3)
```

`@phpstan-ignore` takes identifiers, comma-separated (`@phpstan-ignore argument.type, return.type`), then an optional reason in parentheses. It goes at the end of the line or alone on the line above. With `reportIgnoresWithoutComments: true`, PHPStan reports a missing reason (`ignore.noComment`) and any `@phpstan-ignore-next-line` / `-line` (`ignore.allLineErrors`).

Psalm takes the issue type, comma-separated for several, and any text after it as the reason:

```php
/** @psalm-suppress PossiblyNullArgument Client::send() never returns null since v3 */
return $this->toInvoice($this->client->send($request));
```

Never `@psalm-suppress all`.

### 3. `@var` Is Not Checked Against Reality

PHPStan reports an inline `@var` only when it contradicts a *native* type (`varTag.nativeType`: `@var string` over `$x = 5`). Narrowing a union is accepted silently:

**Incorrect:**

```php
/** @var User $user */
$user = $this->users->find($id);   // User|null; no error, null reaches ->email
return $user->email;
```

**Correct:**

```php
$user = $this->users->find($id) ?? throw new UserNotFound($id);
return $user->email;
```

Use inline `@var` only where the analyser has no type at all and you know the real one (`mixed` from a library that genuinely lacks types), and prefer narrowing (`instanceof`, `is_string()`) even then. The same applies to Larastan `@property` tags (`laravel-larastan.md`).

### 4. Handle the Value, Don't Hide It

| Situation | Fix |
|---|---|
| Value can be null and the method can't continue | Explicit throw: `?? throw new ...`, or `findOrFail()` / `getOneOrNullResult() ?? throw` |
| Null is a valid outcome | Make the return type nullable **and** update callers to handle it |
| `mixed` from input, JSON, config, cache | Narrow at the boundary (`is_array()`, `is_string()`, typed getters) and throw on bad data |
| Error points at a real bug | Fix the bug. Tests should cover it (`verification.md`) |

An explicit throw changes behaviour only for the case the types already said was possible, and fails loudly. That is why it's allowed and `assert()` isn't.

### 5. Psalter Widens Types

`psalm --alter --issues=InvalidNullableReturnType` rewrites `function nameOf(): string` to `string|null`. That's widening. Use `--alter` only for additive fixes such as `MissingReturnType` / `MissingParamType`, always with `--dry-run` first, and review every change.

### 6. Existing Ignores

Leave existing ignores alone unless they become unmatched (`ignore.unmatched`, `ignore.unmatchedIdentifier`, `ignore.unmatchedLine`, Psalm `UnusedPsalmSuppress`). Then delete them: the error they hid is gone.

### Checklist

- [ ] No new ignore, suppress, `ignoreErrors` or `issueHandlers` entry, unless a documented false positive with identifier and reason
- [ ] No type widened to make an error go away
- [ ] No `@var` / `@property` that narrows a type the code doesn't guarantee
- [ ] No `assert()` or cast hiding a real null or wrong type
- [ ] No config change that loosens checks
