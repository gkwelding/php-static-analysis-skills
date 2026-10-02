---
title: Verification and Report
tags: phpstan, psalm, tests, verification, report
---

## Verification and Report

Type fixes change behaviour more often than they look: a new `?? throw`, a `findOrFail()`, a narrowed `mixed`, a native type that now throws `TypeError` on a value that used to pass through. The work isn't done until the analyser and the tests agree.

### 1. Re-run the Analyser

1. On each changed file while working (`vendor/bin/phpstan analyse --no-progress path/to/File.php`, `vendor/bin/psalm --no-progress path/to/File.php`).
2. Then the whole project with the project's command and no path arguments. Changing a signature produces errors in callers you didn't touch, and only a full run reports stale baseline entries and ignores (`baseline.md`).
3. Both tools, if both are configured.
4. Remove every `\PHPStan\dumpType()` and `@psalm-trace` you added.

### 2. Run the Tests

Run the test suite the project uses (`composer test`, `php artisan test`, `vendor/bin/phpunit`, `vendor/bin/pest`), through the same container wrapper as the analyser if there is one. Run at least the tests for the changed classes, then the full suite if it runs in reasonable time.

A test that fails after a type fix has found one of three things:

| Cause | Action |
|---|---|
| The fix changed behaviour by mistake | Change the fix |
| The fix exposed a real bug the test encoded (asserted the buggy result) | Report it; don't change the test to match without asking |
| The test passes a value the new type rightly rejects | Fix the test input and say so |

Don't change production behaviour beyond what the fix needs, and don't edit tests to make a fix pass without explaining why.

If no tests cover a changed path that now throws or returns differently, say so in the report.

### 3. Report

```
## Static analysis: <target>

Tool: PHPStan 2.2 + Larastan 3.12, level 8 (phpstan.neon)
Errors: 37 → 0 (baseline: 112 → 104 entries)

Fixed:
- 14 × missingType.generics: relation and collection return types on 6 models
- 9 × property.nonObject: $order->customer handled as nullable in OrderService (now throws CustomerMissing)
- ...

Behaviour changes:
- OrderService::invoice() throws CustomerMissing instead of a TypeError when the customer is deleted
- InvoiceController::show() uses findOrFail(): missing invoice is now 404, was 500

Ignores added: none (or: each one with file:line, identifier and reason)
Config changes proposed, not made: parseModelCastsMethod: true (lets Larastan read casts() methods)
Tests: 412 passed (vendor/bin/phpunit)
Not fixed: 3 × argument.type in app/Legacy/Import.php: needs a decision on the CSV format
```

Always include: the tool and level, error count before and after, baseline count before and after, every ignore added (should be none), behaviour changes, test results, and anything left with the reason.
