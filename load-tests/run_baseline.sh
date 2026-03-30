#!/usr/bin/env bash
# Run BEFORE any scaling changes — captures single-instance baseline
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RESULTS_DIR="$SCRIPT_DIR/results/baseline"
VENV="$SCRIPT_DIR/.venv/bin/locust"
DOMAIN="esmos-e08g09t05.koreacentral.cloudapp.azure.com"

mkdir -p "$RESULTS_DIR"

# Activate venv if present
if [ -f "$SCRIPT_DIR/.venv/bin/activate" ]; then
    source "$SCRIPT_DIR/.venv/bin/activate"
fi

echo "=== BASELINE: Odoo (100 users, 90s) ==="
locust \
    -f "$SCRIPT_DIR/locustfile_odoo.py" \
    --headless \
    --users 100 \
    --spawn-rate 10 \
    --run-time 90s \
    --csv "$RESULTS_DIR/odoo_baseline" \
    --csv-full-history \
    --html "$RESULTS_DIR/odoo_baseline.html" \
    --host "https://$DOMAIN" \
    2>&1 | tee "$RESULTS_DIR/odoo_baseline.log"

echo ""
echo "=== BASELINE: Moodle (50 users, 90s) ==="
locust \
    -f "$SCRIPT_DIR/locustfile_moodle.py" \
    --headless \
    --users 50 \
    --spawn-rate 5 \
    --run-time 90s \
    --csv "$RESULTS_DIR/moodle_baseline" \
    --csv-full-history \
    --html "$RESULTS_DIR/moodle_baseline.html" \
    --host "https://$DOMAIN:8443" \
    2>&1 | tee "$RESULTS_DIR/moodle_baseline.log"

echo ""
echo "Baseline complete. Results: $RESULTS_DIR"
