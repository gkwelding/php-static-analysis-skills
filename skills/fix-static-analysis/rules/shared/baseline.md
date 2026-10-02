---
title: Baselines
tags: phpstan, psalm, baseline, regenerate, counts
---

## Baselines

A baseline records known errors so they don't fail the build. It may only shrink. Regenerating it is how fixed entries are removed, and also the easiest way to absorb new errors without anyone noticing, so it has strict rules.

### 1. Find It and Count It

| Tool | Where | Count |
|---|---|---|
| PHPStan | `includes:` in the config, usually `phpstan-baseline.neon` (or `.php`) | Sum of the `count:` values |
| Psalm | `errorBaseline="..."` on `<psalm>`, usually `psalm-baseline.xml` | Number of `<code>` elements |

```bash
grep -E '^\s+count:' phpstan-baseline.neon | awk '{s+=$2} END {print s}'
grep -c '<code>' psalm-baseline.xml
```

Record the count before you change anything and again at the end. Both go in the report.

### 2. Never Absorb New Errors

**Incorrect:**

```bash
# 3 new errors after a change → "fix" by regenerating
vendor/bin/phpstan analyse --generate-baseline
vendor/bin/psalm --set-baseline=psalm-baseline.xml
```

Both commands write *every* current error into the baseline. `--set-baseline` is Psalm's "start a new baseline" command, not an update.

### 3. Removing Fixed Entries

**PHPStan.** After fixing, run with no path arguments (file-only runs don't report stale entries, `running-the-analyser.md`). Stale entries show up as:

- `ignore.unmatched`: "Ignored error pattern ... was not matched in reported errors." The error is gone.
- `ignore.count`: "... is expected to occur 3 times, but occurred only 1 time." Some occurrences are gone.

Regenerate **only if those are the only errors in the run**:

```bash
vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon
```

Watch for the other `ignore.count` message, "is expected to occur 1 time, but occurred 2 times": that is a **new** error matching an old entry. Fix it before regenerating.

Run the generator with the project's normal config and no path arguments: it writes only the errors from the files it analysed, so a partial run deletes the entries for every other file. Use the path the config already includes (the default is `phpstan-baseline.neon`). If the last entry is fixed, the generator refuses an empty baseline; pass `--allow-empty-baseline`, or remove the baseline file and its `includes:` line.

**Psalm.** Removing fixed entries has its own command, which never adds:

```bash
vendor/bin/psalm --update-baseline
```

Stale entries show as `UnusedBaselineEntry` ("Baseline for issue "MissingParamType" has 1 extra entry.") while `findUnusedBaselineEntry` is on, which is the default. Psalm matches baseline entries by file, issue type and code snippet, not line, so a new error whose snippet matches an existing entry can hide behind it. Check new code in the diff, not just the run.

### 4. Hand Edits

Deleting a single fixed entry by hand is fine. Editing a `count:` upwards, adding an entry, or widening a `message` pattern is absorbing new errors.

### 5. Baseline Errors Are Still Errors

Entries in the baseline are real errors in existing code. When the task covers a file, fixing its baseline entries is in scope; when it doesn't, leave them and mention the count.

### Checklist

- [ ] Baseline count recorded before and after
- [ ] Regenerated only when the run showed nothing but stale-entry errors
- [ ] Regenerated from a full run with the project's config
- [ ] Psalm: `--update-baseline`, never `--set-baseline`
- [ ] Count went down or stayed the same
