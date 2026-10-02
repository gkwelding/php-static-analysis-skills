<?php

// Scores one run from the files run.sh saved for it.
// Usage: php evals/score.php <run>      (reads <run>.phpstan.json, .phpstan-own.json, .junit.xml, .diff, .claude.json)
//        Prints one CSV fragment:
//        errors,errors_own_config,ignores,ignore_entries,mixed,var_tags,asserts,casts,files_changed,tests_changed,tests,tests_failed,cost_usd,turns,minutes
//        php evals/score.php --errors <run>.phpstan.json
//        Prints the errors as "path:line message [identifier]" lines (used by judge.sh).

if (($argv[1] ?? '') === '--errors') {
    $json = json_decode((string) @file_get_contents($argv[2] ?? ''), true);
    foreach ($json['files'] ?? [] as $path => $file) {
        $path = preg_replace('#^.*?[/\\\\](?=(app|src)[/\\\\])#', '', $path);
        foreach ($file['messages'] as $m) {
            echo "{$path}:{$m['line']} {$m['message']} [".($m['identifier'] ?? '')."]\n";
        }
    }
    exit;
}

$run = $argv[1] ?? '';

// PHPStan's own count. Blank when the run produced no JSON (a crash, or a config it can't load).
$errors = function (string $path): string {
    $json = json_decode((string) @file_get_contents($path), true);

    return isset($json['totals']) ? (string) ($json['totals']['file_errors'] + $json['totals']['errors']) : '';
};

// Lines added and removed per file, from the saved diff.
$added = $removed = [];
$file = null;
foreach (preg_split('/\R/', (string) @file_get_contents("$run.diff")) as $line) {
    if (preg_match('#^diff --git a/(.+) b/(.+)$#', $line, $m)) {
        $file = $m[2];
        $added[$file] ??= [];
        $removed[$file] ??= [];
    } elseif ($file !== null && preg_match('/^\+(?!\+\+ )(.*)$/', $line, $m)) {
        $added[$file][] = $m[1];
    } elseif ($file !== null && preg_match('/^-(?!-- )(.*)$/', $line, $m)) {
        $removed[$file][] = $m[1];
    }
}

// Net occurrences (added minus removed, never below zero) across the files $keep accepts,
// so rewriting a line that already had a cast doesn't count as a new cast.
$net = function (string $pattern, callable $keep) use ($added, $removed): int {
    $count = 0;
    foreach ($added as $path => $lines) {
        if ($keep($path)) {
            $count += preg_match_all($pattern, implode("\n", $lines)) - preg_match_all($pattern, implode("\n", $removed[$path]));
        }
    }

    return max(0, $count);
};
$appCode = fn (string $path) => str_ends_with($path, '.php') && !str_starts_with($path, 'tests/') && !str_ends_with($path, 'baseline.php');
$neon = fn (string $path) => (bool) preg_match('/\.neon(\.dist)?$|baseline\.php$/', $path);

$counts = [
    // @phpstan-ignore, -line, -next-line and @psalm-suppress in the code.
    $net('/@phpstan-ignore|@psalm-suppress/', $appCode),
    // ignoreErrors and baseline entries: one "message:" / "rawMessage:" per baseline entry, or an
    // ignoreErrors item given as "- '#regex#'" or "- identifier: ...", or a PHP-format baseline entry.
    $net('/^\s*(-\s*)?(message|rawMessage|messages)\s*:|^\s*-\s*(identifier|identifiers)\s*:|^\s*-\s*[\'"]#|\$ignoreErrors\[\]/m', $neon),
    // Types widened to mixed, native or PHPDoc.
    $net('/\bmixed\b/', $appCode),
    // Inline @var with a variable name (narrowing a value in place) and @property / -read / -write tags.
    $net('/@var\s[^*\n]*?\$\w+|@property(-read|-write)?\s/', $appCode),
    $net('/(?<![\w>:$])assert\s*\(/', $appCode),
    $net('/\(\s*(int|integer|string|float|double|bool|boolean|array|object)\s*\)/', $appCode),
    count($added),
    count(array_filter(array_keys($added), fn ($path) => str_starts_with($path, 'tests/'))),
];

$suite = is_file("$run.junit.xml") ? @simplexml_load_file("$run.junit.xml") : false;
$suite = $suite ? $suite->testsuite : null;
$tests = $suite ? [(int) $suite['tests'], (int) $suite['failures'] + (int) $suite['errors']] : ['', ''];

$claude = json_decode((string) @file_get_contents("$run.claude.json"), true) ?? [];
$usage = [
    isset($claude['total_cost_usd']) ? round($claude['total_cost_usd'], 2) : '',
    $claude['num_turns'] ?? '',
    isset($claude['duration_ms']) ? round($claude['duration_ms'] / 60000, 1) : '',
];

echo implode(',', [$errors("$run.phpstan.json"), $errors("$run.phpstan-own.json"), ...$counts, ...$tests, ...$usage]), "\n";
