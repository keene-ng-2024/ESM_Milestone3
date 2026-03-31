#!/usr/bin/env bash
# Run this once on the Azure VM to set up the load test environment
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== Setting up load test venv ==="

# Create venv inside load-tests/
python3 -m venv "$SCRIPT_DIR/.venv"

# Activate and install
source "$SCRIPT_DIR/.venv/bin/activate"
pip install --upgrade pip --quiet
pip install -r "$SCRIPT_DIR/requirements.txt"

echo "Done. Activate with:"
echo "  source $SCRIPT_DIR/.venv/bin/activate"
