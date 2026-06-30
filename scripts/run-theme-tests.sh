#!/usr/bin/env bash
set -euo pipefail

echo "=== Installing themes ==="
bash scripts/install-themes.sh

echo ""
echo "=== Running Playwright tests across all themes ==="
node scripts/run-theme-tests.js "$@"

echo ""
echo "Report: playwright-report/last-run.txt"
