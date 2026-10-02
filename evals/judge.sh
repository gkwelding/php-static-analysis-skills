#!/usr/bin/env bash
# Blind side-by-side review of the fixes the two variants made to each fixture in a results folder.
# The diffs are shown as A and B in random order; verdicts are mapped back to with/without.
#
# Usage: evals/judge.sh <results folder>     (run.sh calls this at the end unless JUDGE=0)
# Env:   MODEL             model for claude -p (default: your claude default)
#        JUDGE_BUDGET_USD  spend cap per comparison (default 1)
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
cd "$1" # relative paths from here also work with a native Windows php
budget=${JUDGE_BUDGET_USD:-1}

criteria=(correctness honesty behaviour minimality overall)
props=""
for c in "${criteria[@]}"; do
    props+="\"$c\":{\"type\":\"object\",\"properties\":{\"winner\":{\"type\":\"string\",\"enum\":[\"A\",\"B\",\"tie\"]},\"reason\":{\"type\":\"string\"}},\"required\":[\"winner\",\"reason\"]},"
done
required=$(printf '"%s",' "${criteria[@]}")
schema="{\"type\":\"object\",\"properties\":{${props%,}},\"required\":[${required%,}]}"

# a_was says which variant was shown as A; the reasons refer to the fixes as A and B.
echo "fixture,criterion,winner,a_was,reason" > judge.csv

tail -n +2 results.csv | cut -d, -f1 | sort -u | while read -r fx; do
    [ -f "$fx-with.diff" ] && [ -f "$fx-without.diff" ] || continue

    # Random order, so position can't favour a variant.
    if (( RANDOM % 2 )); then a=with b=without; else a=without b=with; fi

    {
        cat <<'EOF'
Two changes, A and B, were made independently to the same PHP project to clear the PHPStan errors listed below. Compare only the changes.

For each criterion, pick A, B or tie, and give one sentence that cites something specific in the diffs:
- correctness: the types now say what the code really does (precise generics, array shapes, nullability that matches reality).
- honesty: errors are fixed at their cause rather than papered over (ignores, baseline or ignoreErrors entries, types widened to mixed or to nullable without handling null, inline @var or @property narrowing, assert(), casts or ?? defaults that turn a null into a plausible wrong value).
- behaviour: no unintended behaviour change; where behaviour does change (a new exception for a state the types said was possible), it is explicit and sensible.
- minimality: changes only what the fix needs, no unrelated refactoring.
- overall: the change you would rather merge.

Judge quality, not quantity.
EOF
        echo
        echo "=== PHPStan errors before the change ==="
        php "$root/evals/score.php" --errors "$fx-baseline.phpstan.json"
        echo
        echo "=== Change A ==="
        cat "$fx-$a.diff"
        echo
        echo "=== Change B ==="
        cat "$fx-$b.diff"
    } > "$fx.judge-prompt.txt"

    echo "== judging $fx (A = $a)"
    claude -p --tools "" --output-format json --json-schema "$schema" --max-budget-usd "$budget" \
        --no-session-persistence ${MODEL:+--model "$MODEL"} \
        < "$fx.judge-prompt.txt" > "$fx.judge.json" 2> "$fx.judge.err" || echo "   judge failed, see $fx.judge.err"

    php -r '
        [, $json, $a, $b, $fx] = $argv;
        $verdict = json_decode((string) @file_get_contents($json), true)["structured_output"] ?? [];
        $out = fopen("judge.csv", "a");
        foreach ($verdict as $criterion => $v) {
            $winner = ["A" => $a, "B" => $b][$v["winner"]] ?? "tie";
            fputcsv($out, [$fx, $criterion, $winner, $a, $v["reason"]], escape: "");
        }
    ' "$fx.judge.json" "$a" "$b" "$fx"
done

php -r '
    $rows = array_map(fn ($l) => str_getcsv($l, escape: ""), array_slice(file("judge.csv", FILE_IGNORE_NEW_LINES), 1));
    $tally = [];
    foreach ($rows as [, $criterion, $winner]) {
        $tally[$criterion][$winner] = ($tally[$criterion][$winner] ?? 0) + 1;
    }
    printf("\n%-12s %5s %8s %4s\n", "criterion", "with", "without", "tie");
    foreach ($tally as $criterion => $t) {
        printf("%-12s %5d %8d %4d\n", $criterion, $t["with"] ?? 0, $t["without"] ?? 0, $t["tie"] ?? 0);
    }
'
echo
echo "Verdicts with reasons: $1/judge.csv"
