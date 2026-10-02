#!/usr/bin/env bash
# Fix each fixture project's PHPStan errors with and without the fix-static-analysis skill, then score
# the result: errors left at the configured level, cheating counters from the diff, and the fixture's
# own test suite.
#
# Usage: evals/run.sh [all|php|laravel]
# Env:   MODEL       model for claude -p (default: your claude default)
#        BUDGET_USD  spend cap per claude run (default 5)
#        WORK        scratch directory for the scaffolded projects (default evals/.work)
#        JUDGE       0 skips the blind side-by-side review at the end (evals/judge.sh)
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
work=${WORK:-$root/evals/.work}
budget=${BUDGET_USD:-5}
which=${1:-all}
stamp=$(date +%Y%m%d-%H%M%S)
results=$work/results/$stamp
mkdir -p "$results"
csv=$results/results.csv
echo "fixture,variant,errors,errors_own_config,ignores,ignore_entries,mixed,var_tags,asserts,casts,files_changed,tests_changed,tests,tests_failed,cost_usd,turns,minutes" > "$csv"
echo "claude: $(command -v claude)"

git_() { git -c user.name=eval -c user.email=eval@localhost "$@"; }

scaffold() {
    local fx=$1 dir=$work/$1
    if [ ! -d "$dir/.git" ]; then
        rm -rf "$dir"
        case $fx in
            php)
                mkdir -p "$dir"
                cp -r "$root/evals/fixtures/php/." "$dir/"
                (cd "$dir" && composer install -n --quiet) ;;
            laravel)
                composer create-project -n --quiet laravel/laravel "$dir"
                (cd "$dir" && composer require -n --quiet --dev larastan/larastan)
                # The skeleton's CLAUDE.md / AGENTS.md tell the agent to install Laravel Boost first.
                rm -f "$dir/CLAUDE.md" "$dir/AGENTS.md"
                echo "require __DIR__.'/shop.php';" >> "$dir/routes/web.php" ;;
        esac
        (cd "$dir" && git init -q && git config core.autocrlf false)
    fi

    # Copy fixtures on every run so edits reach an existing scaffold.
    # Package changes still need a fresh scaffold: delete $work/<fixture>.
    (cd "$dir" && if git rev-parse -q --verify HEAD > /dev/null; then git reset -q --hard && git clean -qfd; fi)
    cp -r "$root/evals/fixtures/$fx/." "$dir/"
    (cd "$dir" && git add -A && { git diff --cached --quiet || git_ commit -qm baseline; })
}

run_one() {
    local fx=$1 variant=$2 dir=$work/$1 name=$1-$2 prompt
    # Paths below are relative to $dir so they also work with a native Windows php.
    local out=../results/$stamp/$name

    (cd "$dir" && git reset -q --hard && git clean -qfd)
    echo "== $name"
    if [ "$variant" != baseline ]; then
        if [ "$variant" = with ]; then
            mkdir -p "$dir/.claude/skills"
            cp -r "$root"/skills/* "$dir/.claude/skills/"
            prompt="/fix-static-analysis"
        else
            prompt="Fix the PHPStan errors in this project."
        fi
        # Prompt on stdin: as an argument, Git Bash rewrites "/fix-static-analysis" into a file path, and MSYS_NO_PATHCONV
        # would leak into Claude's own shell. --setting-sources project keeps user plugins and hooks out.
        (cd "$dir" && printf '%s' "$prompt" | claude -p --setting-sources project ${MODEL:+--model "$MODEL"} \
            --max-budget-usd "$budget" --no-session-persistence --permission-mode acceptEdits --output-format json \
            --allowedTools "Read,Write,Edit,Glob,Grep,Bash(php:*),Bash(vendor/bin/phpstan:*),Bash(PAO_DISABLE=1 vendor/bin/phpstan:*),Bash(vendor/bin/phpunit:*),Bash(PAO_DISABLE=1 vendor/bin/phpunit:*),Bash(composer dump-autoload:*),Bash(git diff:*),Bash(git status:*)" \
            > "$out.claude.json" 2> "$out.claude.err") || echo "   claude exited non-zero, see $results/$name.claude.err"
        # Keep Claude's final message readable next to the raw JSON.
        (cd "$dir" && php -r '$j = json_decode((string) @file_get_contents($argv[1]), true); file_put_contents($argv[2], $j["result"] ?? "");' \
            "$out.claude.json" "$out.claude.txt")
    fi
    (cd "$dir" && git add -A && git diff --cached -- . ':!.claude' > "$out.diff")

    # PAO_DISABLE=1 turns off laravel/pao, which rewrites PHPStan and PHPUnit output as its own JSON
    # when it detects an agent. Herd's auto_prepend_file is cleared in case a tool objects.
    # "errors" uses the fixture's own phpstan.neon, so a lowered level, a new ignoreErrors entry or an
    # included baseline in the run's config doesn't hide anything; errors_own_config uses the run's.
    local phpstan=(env PAO_DISABLE=1 php -d auto_prepend_file= vendor/bin/phpstan analyse --no-progress --memory-limit=1G --error-format=json)
    (cd "$dir" && git show HEAD:phpstan.neon > phpstan.eval.neon \
        && { "${phpstan[@]}" -c phpstan.eval.neon > "$out.phpstan.json" 2> "$out.phpstan.err" || true; } \
        && rm phpstan.eval.neon \
        && { "${phpstan[@]}" > "$out.phpstan-own.json" 2>> "$out.phpstan.err" || true; })

    # The fixture's tests pin behaviour, so run them as shipped, not as the run left them.
    (cd "$dir" && git checkout -q HEAD -- tests phpunit.xml && git clean -qfd tests \
        && { PAO_DISABLE=1 php -d auto_prepend_file= vendor/bin/phpunit --log-junit "$out.junit.xml" > "$out.phpunit.txt" 2>&1 || true; })

    echo "$fx,$variant,$(cd "$dir" && php "$root/evals/score.php" "$out")" >> "$csv"
}

for fx in php laravel; do
    [ "$which" = all ] || [ "$which" = "$fx" ] || continue
    scaffold "$fx"
    for variant in baseline without with; do
        run_one "$fx" "$variant"
    done
done

echo
column -s, -t < "$csv" 2>/dev/null || cat "$csv"
echo
echo "Logs, diffs and results.csv: $results"

# Blind side-by-side review of each fixture's two diffs (one extra claude -p call per fixture).
[ "${JUDGE:-1}" = 0 ] || bash "$root/evals/judge.sh" "$results"
