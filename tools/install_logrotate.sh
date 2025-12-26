#!/usr/bin/env bash
# Install logrotate config for ISP Billing logs.
# Usage: sudo bash tools/install_logrotate.sh

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run as root (sudo)." >&2
    exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT_DIR/tools/logrotate_isp_billing.conf"
DEST="/etc/logrotate.d/logrotate_isp_billing.conf"

if [ ! -f "$SRC" ]; then
    echo "Source config not found: $SRC" >&2
    exit 1
fi

cp "$SRC" "$DEST"
chmod 644 "$DEST"
echo "Installed logrotate config to $DEST"
echo "Test with: logrotate -fv $DEST"
