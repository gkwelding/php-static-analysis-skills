---
title: Analysis Levels
tags: phpstan, larastan, psalm, levels, errorLevel
---

## Analysis Levels

Checked against PHPStan 2.2 (`conf/config.level*.neon`) and Psalm 6.19 (`docs/running_psalm/error_levels.md`).

### 1. PHPStan: 0 to 10, Higher Is Stricter

Each level includes everything below it. `level: max` is the highest level the installed version has (10 in PHPStan 2).

| Level | Adds | Typical new identifiers |
|---|---|---|
| 0 | Unknown classes and functions, undefined variables, basic checks | `class.notFound`, `function.notFound`, `variable.undefined` |
| 1 | Possibly undefined variables, extra arguments, unknown magic methods and properties | `variable.undefined`, `arguments.count` |
| 2 | Unknown methods on every expression (not only `$this`), PHPDoc validation, generics declarations, invalid casts and operators | `method.notFound`, `binaryOp.invalid`, `return.phpDocType`, `varTag.nativeType` |
| 3 | Return types, types assigned to properties, array offsets | `return.type`, `assign.propertyType`, `offsetAccess.notFound` |
| 4 | Dead code, always-true/false conditions, unused private members, too-wide types | `identical.alwaysFalse`, `instanceof.alwaysTrue`, `deadCode.unreachable`, `method.unused` |
| 5 | Argument types | `argument.type` |
| 6 | Missing type declarations, including array value types and generics | `missingType.return`, `missingType.parameter`, `missingType.property`, `missingType.iterableValue`, `missingType.generics` |
| 7 | Union types that are only partly right (`reportMaybes`, `checkUnionTypes`) | `argument.type`, `method.nonObject` on a union |
| 8 | Nullable types: method calls, property access and arguments on `X\|null` | `method.nonObject`, `property.nonObject`, `argument.type`, `return.type` |
| 9 | Explicit `mixed` may only be passed to `mixed` | `argument.type`, `offsetAccess.nonOffsetAccessible`, `return.type` |
| 10 | Implicit `mixed` too: values from untyped parameters, properties and returns | same as 9, plus `echo.nonString` |

Usual hard steps: 5→6 (every signature needs full types), 7→8 (every nullable path), 8→9 (every boundary that produces `mixed`).

### 2. Psalm: 8 to 1, Lower Is Stricter

`errorLevel="1"` is strictest; with no `errorLevel`, Psalm uses 2. "Raising the level" means lowering the number.

| Moving to | Becomes an error |
|---|---|
| 3 (from 4) | Issues for *possible* problems, e.g. `PossiblyNullReference`, `PossiblyNullArgument`, `PossiblyNullPropertyFetch` |
| 2 (from 3) | Missing types (`MissingParamType`, `MissingReturnType`, `MissingPropertyType`) and most other remaining issues, but not `Mixed*` |
| 1 (from 2) | `Mixed*` issues: `MixedArgument`, `MixedAssignment`, `MixedArrayAccess`, `MixedReturnStatement` and the rest |

Levels 5 to 8 are progressively more permissive. Some issues are errors at every level (listed under "Always treated as errors" in `error_levels.md`).

### 3. Previewing the Next Level

The CLI flag overrides the config for one run; the baseline still applies.

```bash
vendor/bin/phpstan analyse --no-progress --error-format=json --level=7   # config says 6
vendor/bin/psalm --no-progress --output-format=json --error-level=2      # config says 3
```

### 4. Raising Without Cheating

**Incorrect:**

```neon
includes:
    - phpstan-baseline.neon   # regenerated after the bump: 340 new entries
parameters:
    level: 7
```

**Correct:** fix the new level's errors first, then change `level`. The baseline only shrinks (`shared/baseline.md`).

Other ways to fake a level, all forbidden:

- `ignoreErrors` by identifier for the new level's checks (`missingType.iterableValue`, `missingType.generics` at 6)
- Turning off the parameters the level enables (`reportMaybes`, `checkNullables`, `checkExplicitMixed`, `checkImplicitMixed`) or `treatPhpDocTypesAsCertain`
- `<issueHandlers>` downgrading the issues the new Psalm level turns into errors
- Excluding the hard directories (`excludePaths`, `<ignoreFiles>`) at the same time as the bump

One level per run. Two levels at once mixes two kinds of fixes and makes the diff hard to review.
