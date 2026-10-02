# PHP Static Analysis Skills

Agent skills for fixing PHPStan, Larastan and Psalm errors properly, and for raising a project's analysis level, without cheating: no new ignores, no baseline growth, no types widened to `mixed`, no `@var` lies.

Left to themselves, coding agents tend to clear analyser errors the quickest way: `@phpstan-ignore-next-line`, a regenerated baseline, `?string` where the value should never be null, `/** @var User $user */` over a `User|null`. These skills make the agent fix the code or the types instead, check the fix with a full re-run and the test suite, and report counts before and after.

**Detect → Measure → Fix → Re-run → Test → Report**

## Skills

| Skill | What it does |
|---|---|
| `fix-static-analysis` | Clears the errors a run reports, for given files or the whole project. Fixes types and code, removes stale ignores and fixed baseline entries, re-runs, runs the tests and reports. |
| `raise-analysis-level` | Moves PHPStan up one `level` (or Psalm down one `errorLevel`): checks the current level is clean, previews the next one, fixes what it reports, then changes the config. The baseline doesn't grow. |

## What's Covered

**No cheating:** what counts as a genuine false positive, the identifier form of `@phpstan-ignore` with a reason, Psalm suppressions with a reason, why inline `@var` and `@property` narrowing go unchecked, `assert()` vs an explicit throw, casts that hide nulls, config that loosens checks, Psalter's nullable "fixes".

**Types:** native types first; `list<>` vs `array<int, T>`, array shapes and type aliases, `non-empty-string` and int ranges, `class-string<T>` with `@template`, generics on collections, conditional return types, `@phpstan-assert`.

**Errors by identifier:** missing types (level 6), nullability (level 8), `mixed` (levels 9-10), unions (level 7), always-true/false conditions, with the matching Psalm issue types.

**Baselines:** counting entries, regenerating only to remove fixed entries, the partial-run and `ignore.count` traps, Psalm `--update-baseline` vs `--set-baseline` and snippet matching.

**Laravel / Larastan:** relation generics (`HasMany<Post, $this>`), `Builder<self>` scopes, why Larastan ignores a `casts()` method by default (`parseModelCastsMethod`), `@property` overrides, `Attribute<TGet, TSet>` accessors, Eloquent vs Support collections, `find()` vs `findOrFail()`, `env()` outside config, typed `Config::string()` / `integer()` getters and the `.env` string trap.

**Symfony / Doctrine:** `containerXmlPath`, `objectManagerLoader`, repository generics and `findBy()` field checks, `doctrine.columnType` mismatches, final entities, the Psalm Symfony plugin.

**Levels:** what each PHPStan level (0-10) and Psalm `errorLevel` (8-1) adds, with the identifiers you'll see, and how to preview the next one.

## Install

### Claude Code plugin

```
/plugin marketplace add gkwelding/php-static-analysis-skills
/plugin install php-static-analysis-skills@php-static-analysis-skills
```

Commands become `/php-static-analysis-skills:fix-static-analysis <target>` and `/php-static-analysis-skills:raise-analysis-level <target>`.

### Copy into a project or user skills folder

```
cp -r skills/fix-static-analysis skills/raise-analysis-level ~/.claude/skills/
# or per project:
cp -r skills/* .claude/skills/
```

### claude.ai

Build the packages, then upload `dist/fix-static-analysis.skill` and `dist/raise-analysis-level.skill` (Settings → Capabilities → Skills):

```
sh scripts/build-skills.sh
```

The script packages the committed files at `HEAD`; commit edits first.

## Usage

```
/fix-static-analysis                       # every error from a full run
/fix-static-analysis app/Services
/fix-static-analysis src/Controller/InvoiceController.php
/raise-analysis-level                      # configured tool, one level up
/raise-analysis-level psalm
```

Pasting analyser output and asking to fix it works too.

## Ground Rules the Skills Enforce

- Fix the code or the types; ignores only for documented false positives, with an identifier and a reason
- The baseline only shrinks, and is regenerated only when the run shows nothing but stale entries
- Config that loosens checks is never changed; config that gives the analyser more information is proposed, not slipped in
- Every run ends with a full re-run and the test suite, because type fixes can change behaviour
- Behaviour changes (a new throw, `findOrFail()` turning a 500 into a 404) are reported
- `vendor/` is never edited; wrong vendor types get a stub file

## Layout

```
skills/
├── fix-static-analysis/
│   ├── SKILL.md
│   └── rules/shared/        # no-cheating, running, types, common errors, baseline,
│                            # Laravel/Larastan, Symfony/Doctrine, verification
└── raise-analysis-level/
    ├── SKILL.md
    └── rules/
        ├── levels.md        # what each level adds, previewing, raising without cheating
        └── shared/          # copy of the above, CI-checked identical
scripts/build-skills.sh      # packages dist/*.skill for claude.ai
```

`rules/shared/` exists in both skills so each can be installed alone. CI (`.github/workflows/check-rules.yml`) fails if the copies differ. Check locally with:

```
diff -r skills/fix-static-analysis/rules/shared skills/raise-analysis-level/rules/shared
```

## Status

First version. Config keys, CLI flags, error identifiers, Psalm issue types and PHPDoc syntax have been checked against PHPStan 2.2, Larastan 3.12 (Laravel 13), phpstan-symfony 2.0, phpstan-doctrine 2.0 (Symfony 8.1, Doctrine ORM 3.7), Psalm 6.19 and psalm/plugin-symfony 5.3, mostly by running them on small examples. Treat it as a strong starting point and adjust to your house style.

There are no evals yet. The plan is a fixture project per framework (plain PHP, Laravel, Symfony) seeded with known errors at several levels, real bugs among them, and a test suite. Each variant (with and without the skills) would be scored on:

- errors cleared at the target level
- ignores, suppressions, `ignoreErrors` entries and baseline entries added (should be zero)
- types widened, inline `@var` / `@property` added, `assert()` and casts added
- seeded bugs fixed rather than hidden
- tests still passing, and behaviour changes reported
- cost per run

## Licence

MIT. See [LICENSE](LICENSE).
