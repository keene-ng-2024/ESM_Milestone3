#!/usr/bin/env bash
# Prints a side-by-side comparison of baseline vs scaled metrics
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
B="$SCRIPT_DIR/results/baseline"
S="$SCRIPT_DIR/results/scaled"

print_comparison() {
    local service=$1
    local b_file="$B/${service}_baseline_stats.csv"
    local s_file="$S/${service}_scaled_stats.csv"

    if [ ! -f "$b_file" ]; then
        echo "Missing baseline results: $b_file"
        return
    fi
    if [ ! -f "$s_file" ]; then
        echo "Missing scaled results: $s_file"
        return
    fi

    # Locust stats CSV columns:
    # Type,Name,Request Count,Failure Count,Median Response Time,Average Response Time,
    # Min Response Time,Max Response Time,Average Content Size,Requests/s,Failures/s,
    # 50%,66%,75%,80%,90%,95%,98%,99%,99.9%,99.99%,100%

    B_LINE=$(grep "Aggregated" "$b_file")
    S_LINE=$(grep "Aggregated" "$s_file")

    B_COUNT=$(echo "$B_LINE"  | cut -d',' -f3)
    S_COUNT=$(echo "$S_LINE"  | cut -d',' -f3)
    B_FAIL=$(echo "$B_LINE"   | cut -d',' -f4)
    S_FAIL=$(echo "$S_LINE"   | cut -d',' -f4)
    B_MED=$(echo "$B_LINE"    | cut -d',' -f5)
    S_MED=$(echo "$S_LINE"    | cut -d',' -f5)
    B_AVG=$(echo "$B_LINE"    | cut -d',' -f6)
    S_AVG=$(echo "$S_LINE"    | cut -d',' -f6)
    B_RPS=$(echo "$B_LINE"    | cut -d',' -f10)
    S_RPS=$(echo "$S_LINE"    | cut -d',' -f10)
    B_P95=$(echo "$B_LINE"    | cut -d',' -f17)
    S_P95=$(echo "$S_LINE"    | cut -d',' -f17)

    echo ""
    echo "========================================"
    echo " ${service^^} — Baseline vs Scaled"
    echo "========================================"
    printf "%-22s %12s %12s\n" "Metric" "Baseline" "Scaled"
    printf "%-22s %12s %12s\n" "------" "--------" "------"
    printf "%-22s %12s %12s\n" "Total requests"    "$B_COUNT"  "$S_COUNT"
    printf "%-22s %12s %12s\n" "Failures"          "$B_FAIL"   "$S_FAIL"
    printf "%-22s %12s %12s\n" "Median resp (ms)"  "$B_MED"    "$S_MED"
    printf "%-22s %12s %12s\n" "Avg resp (ms)"     "$B_AVG"    "$S_AVG"
    printf "%-22s %12s %12s\n" "p95 resp (ms)"     "$B_P95"    "$S_P95"
    printf "%-22s %12s %12s\n" "Requests/sec"      "$B_RPS"    "$S_RPS"
}

print_comparison "odoo"
print_comparison "moodle"

echo ""
echo "HTML reports:"
echo "  Baseline — $(ls "$B"/*.html 2>/dev/null | tr '\n' ' ')"
echo "  Scaled   — $(ls "$S"/*.html 2>/dev/null | tr '\n' ' ')"
