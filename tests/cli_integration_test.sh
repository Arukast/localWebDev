#!/usr/bin/env bash
# CLI Integration Test Harness for ./dev
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

echo "=== Running ./dev CLI Integration Tests ==="

# Check Docker availability
if ! command -v docker &>/dev/null || ! docker info &>/dev/null; then
    echo "⚠️ Docker daemon is not accessible. Skipping Docker integration tests."
    exit 0
fi

# 1. SSL Certificate Generation Check
echo "Testing SSL generation..."
output_ssl=$("$DEV_BIN" ssl 2>&1)
assert_contains "SSL cert output indicates success" "$output_ssl" "SSL certificate"

if [[ -f "${SCRIPT_DIR}/nginx/certs/local-dev.crt" && -f "${SCRIPT_DIR}/nginx/certs/local-dev.key" ]]; then
    echo "  ✅ PASS: SSL certificate files created at nginx/certs/"
    PASSED=$((PASSED + 1))
else
    echo "  ❌ FAIL: SSL certificate files missing"
    FAILED=$((FAILED + 1))
fi

# 2. Container Status Check (Before Up)
if "$DEV_BIN" ps &>/dev/null; then
    echo "  ✅ PASS: Container status before up executed cleanly"
    PASSED=$((PASSED + 1))
else
    echo "  ❌ FAIL: Container status before up failed"
    FAILED=$((FAILED + 1))
fi

# 3. Spin up DNS service only (fast integration test)
echo "Spinning up dnsmasq service..."
output_up=$("$DEV_BIN" up dnsmasq 2>&1) || true
assert_contains "Docker up output starts service" "$output_up" "dnsmasq"

# 4. Check status while running
output_ps_running=$("$DEV_BIN" status 2>&1)
assert_contains "Status output shows local-dnsmasq" "$output_ps_running" "local-dnsmasq"

# 5. Bring down service
echo "Bringing down service..."
output_down=$("$DEV_BIN" down 2>&1) || true
assert_contains "Docker down succeeds" "$output_down" "Stopped"

# 6. Snapshot Lifecycle Test
echo "Testing Snapshot Lifecycle..."
output_snap_save=$("$DEV_BIN" snapshot save test_integration_snap --notes="Automated test snapshot" 2>&1) || true
assert_contains "Snapshot save succeeds" "$output_snap_save" "saved successfully"

output_snap_list=$("$DEV_BIN" snapshot list 2>&1) || true
assert_contains "Snapshot list contains saved snapshot" "$output_snap_list" "test_integration_snap"

output_snap_restore=$("$DEV_BIN" snapshot restore test_integration_snap --force 2>&1) || true
assert_contains "Snapshot restore succeeds with --force" "$output_snap_restore" "restored successfully"

output_snap_del=$("$DEV_BIN" snapshot delete test_integration_snap 2>&1) || true
assert_contains "Snapshot delete succeeds" "$output_snap_del" "Deleted snapshot"

echo ""
echo "=== Integration Test Summary ==="
echo "Passed: $PASSED | Failed: $FAILED"

if [[ $FAILED -gt 0 ]]; then
    exit 1
fi
exit 0
