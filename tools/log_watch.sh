#!/usr/bin/env bash
# Simple log follower for ISP Billing logs.
# Usage: bash tools/log_watch.sh [log1 log2 ...]

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="$ROOT_DIR/storage/logs"
DEFAULT_LOGS=("monitor.log" "error.log" "alerts.log" "auto_billing.log")

if [ ! -d "$LOG_DIR" ]; then
    mkdir -p "$LOG_DIR"
fi

if [ "$#" -gt 0 ]; then
    LOGS=("$@")
else
    LOGS=("${DEFAULT_LOGS[@]}")
fi

FULL_PATHS=()
for log in "${LOGS[@]}"; do
    FULL_PATHS+=("$LOG_DIR/$log")
done

echo "Following logs (Ctrl+C to exit):"
printf ' - %s\n' "${FULL_PATHS[@]}"
echo

tail -F "${FULL_PATHS[@]}"
