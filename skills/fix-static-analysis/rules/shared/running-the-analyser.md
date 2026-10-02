---
title: Running the Analyser
tags: phpstan, larastan, psalm, cli, config, output
---

## Running the Analyser

### 1. Find the Tool and Its Config

| Signal | Tool |
|---|---|
| `phpstan.neon`, `phpstan.neon.dist`, `phpstan.dist.neon` (or the same with a leading dot) | PHPStan; found automatically in the working directory, otherwise pass `-c` |
| `larastan/larastan` in `composer.json` | PHPStan with Larastan (`includes: - vendor/larastan/larastan/extension.neon`, or loaded by `phpstan/extension-installer`) |
| `phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine` | PHPStan extensions; same two loading routes |
| `psalm.xml`, `psalm.xml.dist` | Psalm; plugins under `<plugins>` |

Read the config before the first run:

- **PHPStan:** `level`, `paths`, `excludePaths`, `includes` (baseline file, extensions), `ignoreErrors`, extension settings (`symfony:`, `doctrine:`, Larastan parameters).
- **Psalm:** `errorLevel`, `errorBaseline`, `<projectFiles>`, `<issueHandlers>`, `<plugins>`.

If both tools are configured, both must stay clean: a fix for one can trigger the other.

Use the command the project uses. `composer.json` scripts, the `Makefile` and CI config show the config path, memory limit and any wrapper. If PHP runs in a container (Sail, DDEV, `docker compose exec`), run the analyser there too: a different PHP version or missing extension changes results.

### 2. Commands

PHPStan:

```bash
vendor/bin/phpstan analyse --no-progress --error-format=json          # all configured paths
vendor/bin/phpstan analyse --no-progress app/Services/OrderService.php # one file or directory
vendor/bin/phpstan analyse --no-progress --level=7                     # another level, config unchanged
vendor/bin/phpstan clear-result-cache
```

In JSON, `totals.file_errors` is the count and every message has an `identifier`. Error identifiers are documented at `https://phpstan.org/error-identifiers/<identifier>`. `--error-format=raw` prints one error per line with `[identifier=...]` when run with `-v` or inside an AI agent (PHPStan detects `CLAUDECODE`, `CURSOR_AGENT`, `GEMINI_CLI`, `CODEX_SANDBOX` and similar). Add `--memory-limit=1G` if the run dies of memory.

Psalm:

```bash
vendor/bin/psalm --no-progress --output-format=json
vendor/bin/psalm --no-progress src/Service/Catalogue.php
vendor/bin/psalm --no-progress --error-level=2                         # another level, config unchanged
vendor/bin/psalm --clear-cache
```

Psalm JSON is a list; each entry has `type` (the issue name, e.g. `PossiblyNullReference`), `severity`, `file_name`, `line_from` and `message`.

**Laravel 13 skeleton:** `laravel/pao` (in `require-dev`) replaces PHPStan's output with its own JSON when it detects an agent, even if you pass `--error-format`: `{"tool":"phpstan","result":"failed","errors":N,"error_details":{"<file>":[{"line":..,"message":..,"identifier":..}]}}`. Read that, or prefix the command with `PAO_DISABLE=1` to get PHPStan's own format.

### 3. Partial Runs Don't Report Stale Ignores

When every path given to PHPStan is a file, it skips "was not matched" reports for baseline entries and `ignoreErrors` (`ignore.unmatched`). Use file runs while fixing, but always finish with a run with no path arguments so stale entries surface. Psalm reports unused baseline entries as `UnusedBaselineEntry` (`findUnusedBaselineEntry`, on by default).

### 4. Seeing What the Analyser Sees

When an error doesn't make sense, dump the type instead of guessing:

```php
\PHPStan\dumpType($order->customer);   // reported as "Dumped type: App\Models\Customer|null"
```

```php
/** @psalm-trace $customer */
$customer = $order->customer;          // reported as a Trace issue
```

Remove every dump before finishing; both are reported as errors.

### 5. Sort the Output Before Fixing

Group errors by identifier (PHPStan) or issue type (Psalm) and by the symbol they mention. One wrong return type often produces a dozen errors in callers; fix the declaration first and re-run before touching the callers.
