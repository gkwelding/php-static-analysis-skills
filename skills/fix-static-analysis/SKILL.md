---
name: fix-static-analysis
description: "Fix the errors PHPStan, Larastan or Psalm reports, for given files or the whole project, by correcting the code and its types instead of suppressing them. Use whenever the user asks to fix, clear or resolve static analysis, PHPStan, Larastan or Psalm errors, including 'make phpstan pass', 'fix these psalm issues', 'phpstan is failing in CI', 'clean up the larastan errors in app/Models', or pastes analyser output. Also covers removing fixed entries from a baseline. Not for raising the configured level (use raise-analysis-level), for setting up PHPStan or Psalm in a project that has neither, or for code style tools such as Pint or PHP-CS-Fixer."
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Fix Static Analysis

Clear the errors PHPStan (with Larastan, phpstan-symfony, phpstan-doctrine) or Psalm reports by fixing the code or the types, then prove it with a clean run and passing tests.

**Target:** $ARGUMENTS

If no target was given (the line above is empty or shows a literal `$ARGUMENTS` placeholder), fix every error from a full run of the configured analyser. If the user pasted analyser output, that output is the target. If neither PHPStan nor Psalm is configured, say so and stop.

## Quality Standards

- An error is fixed when the code or its types are right. Silencing it is not a fix (`shared/no-cheating.md`).
- Read the code, its callers and the types it uses before changing anything. Don't guess at a type.
- Change as little as the fix needs. No refactoring on the side.
- Counts before and after, for errors and baseline entries, or it didn't happen.

---

## Step 1: Detect and Measure

1. **Find the tool and config** (`shared/running-the-analyser.md`): PHPStan or Psalm or both, config file, level, baseline, extensions and plugins, and the command the project uses (composer script, CI, container wrapper).
2. **Run it** on the target, or on everything if there is no target, with JSON output.
3. **Record** the error count and the baseline entry count (`shared/baseline.md`).
4. **Group** the errors by identifier / issue type and by the symbol they name.

## Step 2: Fix

Work through the groups, declarations before call sites: one wrong return type can cause a dozen errors downstream.

For each error:

1. Read the reported line, the declaration of everything it touches, and where the value comes from.
2. Decide whether it's a real bug, a missing or wrong type, or (rarely) a genuine false positive.
3. Apply the fix from the rules: native type, precise PHPDoc (`shared/types.md`), the pattern for that identifier (`shared/common-errors.md`), or the framework rule (`shared/laravel-larastan.md`, `shared/symfony-doctrine.md`).
4. Re-run on the file. Max 3 attempts per error; then leave it and report it with what you found.

A genuine false positive gets the narrowest ignore with an identifier and a reason, and is listed in the report. A missing extension setting (`containerXmlPath`, `objectManagerLoader`, `parseModelCastsMethod`) is proposed, not silently added.

## Step 3: Verify

1. Full run with no path arguments, both tools if both are configured (`shared/verification.md`).
2. Delete ignores that became unmatched. Remove fixed baseline entries only as `shared/baseline.md` allows.
3. Run the tests. Analyse failures before touching any test.
4. Report in the format in `shared/verification.md`.

---

## Troubleshooting

**Analyser won't run** (missing config, out of memory, wrong PHP version). Try the project's own command, `--memory-limit=1G`, or the container wrapper. If it still fails, report the error and stop. Don't fix code against a run you couldn't complete.

**Hundreds of errors.** Fix by group, re-running after each, and keep going. If it's clearly too large for one pass, finish whole directories rather than spreading partial fixes, and report what's left by identifier.

**The error is in `vendor/`, or a vendor PHPDoc is wrong.** Never edit `vendor/`. Use a stub file (`stubFiles` / `<stubs>`) for the wrong signature, or upgrade the extension if a newer one models the API. Ask before changing `composer.json`.

**The fix needs a design decision** (should this return null or throw? is this column nullable?). Make the choice the code's own behaviour and tests point to. If they don't settle it, leave the error, explain the options in the report, and don't ignore it.

**An existing ignore or baseline entry hides a real bug in code you're changing.** Fix it if it's within the target. Otherwise mention it.

**PHPStan and Psalm disagree.** Use syntax both read (plain `@param`, `@return`, `@template`). Use tool-prefixed tags only where one tool needs something the other can't express.

---

## Example

```
User: /fix-static-analysis app/Services

Step 1: PHPStan 2.2 + Larastan 3.12, level 8, phpstan.neon includes
        phpstan-baseline.neon (112 entries). `vendor/bin/phpstan analyse app/Services`:
        12 errors: 4 missingType.generics (relations used in PostService),
        3 property.nonObject, 2 return.type, 1 larastan.noEnvCallsOutsideOfConfig,
        1 missingType.iterableValue, 1 method.nonObject.

Step 2: Adds HasMany<Post, $this> / BelongsTo<User, $this> to the models;
        $post->user handled with `?? throw new LogicException(...)`;
        Post::find() → findOrFail() in latest() (callers treat a missing post as
        an error); env('API_KEY') moved to config/services.php and read with
        Config::string(); published_at was string|null because casts() isn't
        read; proposes parseModelCastsMethod: true instead of a @property tag.

Step 3: Full run: 0 errors, 2 ignore.unmatched for fixed baseline entries →
        regenerates the baseline (112 → 110). `php artisan test`: 86 passed.
        Reports one behaviour change: latest() now 404s on a missing post.
```

---

## Rules Reference

Paths are relative to `./rules/`.

> `shared/` is duplicated in `raise-analysis-level/rules/shared/`. CI keeps both copies identical.

### Always

- `shared/no-cheating.md` - what counts as a fix, false positives, ignore syntax
- `shared/running-the-analyser.md` - detection, commands, output, partial runs
- `shared/types.md` - native types, PHPDoc arrays, scalars, generics, conditional types, assertions
- `shared/common-errors.md` - fixes by identifier / issue type
- `shared/baseline.md` - counting, never absorbing, removing fixed entries
- `shared/verification.md` - full re-run, tests, report

### By Target

| Target | Also read |
|---|---|
| **Laravel** / Larastan installed | `shared/laravel-larastan.md` |
| **Symfony**, Doctrine, phpstan-symfony / phpstan-doctrine, Psalm Symfony plugin | `shared/symfony-doctrine.md` |
