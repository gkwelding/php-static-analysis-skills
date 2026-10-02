---
name: raise-analysis-level
description: "Raise a PHP project's static analysis strictness by one step: PHPStan/Larastan `level` up by one, or Psalm `errorLevel` down by one, fixing everything the new level reports so the baseline doesn't grow. Use whenever the user asks to raise, bump, increase or tighten the PHPStan, Larastan or Psalm level, 'go to level 8', 'make phpstan stricter', 'move psalm to errorLevel 2', or asks what it would take to reach the next level. Not for fixing errors at the current level only (use fix-static-analysis), for installing PHPStan or Psalm, or for jumping several levels at once."
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Raise Analysis Level

Move the project one level stricter (PHPStan `level: N` → `N+1`, Psalm `errorLevel="N"` → `N-1`) by fixing what the new level reports, then change the config. The baseline doesn't grow.

**Target:** $ARGUMENTS

The target is the tool and optionally the level ("phpstan", "psalm", "level 7"). If no target was given (the line above is empty or shows a literal `$ARGUMENTS` placeholder), use the configured tool and go one level up; if both PHPStan and Psalm are configured, ask which. A requested level more than one step away: do the next step only and say so. Already at PHPStan `level: max` / 10 or Psalm `errorLevel="1"`: say so and stop.

## Quality Standards

- The level changes in the config only when the new level is clean. Never bump and baseline the difference (`levels.md`).
- Every rule in `shared/no-cheating.md` applies, plus the level-specific cheats in `levels.md`.
- One level per run.
- Counts at every step: current level, next-level preview, final.

---

## Step 1: Detect and Check the Current Level

1. **Find the tool, config, level and baseline** (`shared/running-the-analyser.md`). Record the baseline entry count (`shared/baseline.md`).
2. **Run at the current level.** If it reports errors, the current level isn't clean: fix those first following `shared/` (as `fix-static-analysis` would), or stop and ask if there are many. Don't stack a new level on a failing one.

## Step 2: Preview the Next Level

1. Run with the CLI override, config unchanged: `--level=N+1` (PHPStan) or `--error-level=N-1` (Psalm).
2. Group the new errors by identifier / issue type and directory. `levels.md` says what the new level checks.
3. Print a short summary before fixing:

```
PHPStan level 6 → 7 preview: 48 new errors
- 31 × argument.type (union types), 22 in app/Services
- 12 × method.nonObject on unions
- 5 × return.type
Baseline: 112 entries (unchanged by the preview)
```

Then continue to Step 3. The summary lets the user check the plan; the run stays unattended.

## Step 3: Fix

Fix the new errors with the `shared/` rules, declarations first, re-running the preview command on changed files. Where one change clears many errors (a return type, a relation's generics), make it and re-run before going on.

If the new level is too large to finish in one pass, finish whole directories, leave the config level unchanged, and report what's left by identifier and directory. Partial progress is useful; a level that's switched on but baselined isn't.

## Step 4: Raise and Verify

1. Change the level in the config: `level: 7` in `phpstan.neon`, or `errorLevel="2"` on `<psalm>`. Nothing else in the config.
2. Full run with the project's command and no path arguments. It must report zero errors, apart from stale-entry errors for baseline entries your fixes removed; clear those as `shared/baseline.md` allows. The baseline count must not go up.
3. Update CI or composer scripts only if they pass a level explicitly (`--level=6`); tell the user.
4. Run the tests (`shared/verification.md`).
5. Report: old and new level, preview count, errors fixed by identifier, baseline count before and after, behaviour changes, test results.

---

## Troubleshooting

**Current level isn't clean.** Fix it first or ask; report that the raise didn't start.

**The baseline is large.** Leave it: entries are errors from before, and they still match at the new level. Any of them your fixes remove come out in Step 4.

**The new level reports errors in files with baseline entries.** The new-level errors are new; fix them. Don't edit the baseline to cover them.

**A design decision blocks a group of errors** (does this return null? what shape is this payload?). Fix everything else, leave the config level unchanged, and report the open question with the error count it blocks.

**`level` is set somewhere other than `phpstan.neon`** (an included file, `--level` in a composer script). Change it where it's set; mention any other place that overrides it.

**Larastan or Symfony extension errors appear at the new level** (`missingType.generics` on relations at 6, `property.nonObject` on `$model->relation` at 8). Use `shared/laravel-larastan.md` / `shared/symfony-doctrine.md`.

---

## Example

```
User: /raise-analysis-level phpstan

Step 1: PHPStan 2.2 + Larastan 3.12, level 7, phpstan-baseline.neon 112 entries.
        Level 7 run: 0 errors.

Step 2: `vendor/bin/phpstan analyse --level=8`: 41 new errors.
        26 × property.nonObject ($order->customer, $post->user: BelongsTo is nullable),
        9 × method.nonObject (Model::find() results), 6 × argument.type (?string to string).

Step 3: `?? throw new LogicException(...)` where a missing relation is a bug;
        find() → findOrFail() in two controllers (now 404 instead of 500);
        ?string parameter in SlugService where null is a valid input.

Step 4: level: 8 in phpstan.neon. Full run: 0 errors, 1 ignore.unmatched
        → baseline regenerated, 112 → 111. `php artisan test`: 214 passed.
        Reports the two controller behaviour changes.
```

---

## Rules Reference

Paths are relative to `./rules/`.

> `shared/` is duplicated in `fix-static-analysis/rules/shared/`. CI keeps both copies identical.

### Always

- `levels.md` - what each PHPStan and Psalm level adds, previewing, raising without cheating
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
