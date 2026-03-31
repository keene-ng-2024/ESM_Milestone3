#!/usr/bin/env bash
# Run AFTER scaling to 2 replicas each
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RESULTS_DIR="$SCRIPT_DIR/results/scaled"
DOMAIN="esmos-e08g09t05.koreacentral.cloudapp.azure.com"

mkdir -p "$RESULTS_DIR"

# Activate venv if present
if [ -f "$SCRIPT_DIR/.venv/bin/activate" ]; then
    source "$SCRIPT_DIR/.venv/bin/activate"
fi

echo "=== SCALED: Odoo (100 users, 90s) ==="
locust \
    -f "$SCRIPT_DIR/locustfile_odoo.py" \
    --headless \
    --users 100 \
    --spawn-rate 10 \
    --run-time 90s \
    --csv "$RESULTS_DIR/odoo_scaled" \
    --csv-full-history \
    --html "$RESULTS_DIR/odoo_scaled.html" \
    --host "https://$DOMAIN" \
    2>&1 | tee "$RESULTS_DIR/odoo_scaled.log"

echo ""
echo "=== SCALED: Moodle (50 users, 90s) ==="
locust \
    -f "$SCRIPT_DIR/locustfile_moodle.py" \
    --headless \
    --users 50 \
    --spawn-rate 5 \
    --run-time 90s \
    --csv "$RESULTS_DIR/moodle_scaled" \
    --csv-full-history \
    --html "$RESULTS_DIR/moodle_scaled.html" \
    --host "https://$DOMAIN:8443" \
    2>&1 | tee "$RESULTS_DIR/moodle_scaled.log"

echo ""
echo "Scaled test complete. Results: $RESULTS_DIR"
