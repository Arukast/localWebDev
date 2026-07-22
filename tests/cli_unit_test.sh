#!/usr/bin/env bash
# CLI Unit Test Harness for ./dev
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEV_BIN="${SCRIPT_DIR}/dev"

PASSED=0
FAILED=0

assert_contains() {
    local label="$1"
    local output="$2"
    local substring="$3"

    if echo "$output" | grep -q "$substring"; then
        echo "  ✅ PASS: $label"
        PASSED=$((PASSED + 1))
    else
        echo "  ❌ FAIL: $label (Expected to find: '$substring')"
        FAILED=$((FAILED + 1))
    fi
}

assert_exit_code() {
    local label="$1"
    local expected_code="$2"
    shift 2
    local actual_code=0
    "$@" >/dev/null 2>&1 || actual_code=$?

    if [[ "$actual_code" -eq "$expected_code" ]]; then
        echo "  ✅ PASS: $label (Exit code $expected_code)"
        PASSED=$((PASSED + 1))
    else
        echo "  ❌ FAIL: $label (Expected exit code $expected_code, got $actual_code)"
        FAILED=$((FAILED + 1))
    fi
}

echo "=== Running ./dev CLI Unit Tests ==="

# 1. Executable Permission Test
if [[ -x "$DEV_BIN" ]]; then
    echo "  ✅ PASS: ./dev is executable"
    PASSED=$((PASSED + 1))
else
    echo "  ❌ FAIL: ./dev is not executable"
    FAILED=$((FAILED + 1))
fi

# 2. Help Output Test
output_help=$("$DEV_BIN" help 2>&1)
assert_contains "Help output mentions usage" "$output_help" "Usage:"
assert_contains "Help output mentions docker commands" "$output_help" "up"
assert_contains "Help output mentions test command" "$output_help" "test"

# 3. Invalid Command Test
assert_exit_code "Invalid command returns exit code 1" 1 "$DEV_BIN" non_existent_subcommand_123

# 4. Completion Generator Tests
output_bash=$("$DEV_BIN" completion bash 2>&1)
assert_contains "Bash completion generated" "$output_bash" "_dev_completion"

output_zsh=$("$DEV_BIN" completion zsh 2>&1)
assert_contains "Zsh completion generated" "$output_zsh" "#compdef dev"

output_fish=$("$DEV_BIN" completion fish 2>&1)
assert_contains "Fish completion generated" "$output_fish" "complete -c dev"

# 5. Project Detection Unit Test
mkdir -p "${SCRIPT_DIR}/projects/dummy_test_proj"
pushd "${SCRIPT_DIR}/projects/dummy_test_proj" >/dev/null
output_detect=$("$DEV_BIN" list 2>&1)
assert_contains "Projects list includes dummy project" "$output_detect" "dummy_test_proj"
popd >/dev/null
rm -rf "${SCRIPT_DIR}/projects/dummy_test_proj"

echo ""
echo "=== Unit Test Summary ==="
echo "Passed: $PASSED | Failed: $FAILED"

if [[ $FAILED -gt 0 ]]; then
    exit 1
fi
exit 0
