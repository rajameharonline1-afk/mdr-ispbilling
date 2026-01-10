#!/usr/bin/env bash
# Install cron job for bKash RTN processor
# Creates /etc/cron.d/bkash_rtn to run the processor every minute.

set -euo pipefail

ROOT_DIR="/var/www/isp_billing"
CRON_FILE="/etc/cron.d/bkash_rtn"
LOG_DIR="$ROOT_DIR/logs"
LOG_FILE="$LOG_DIR/bkash_rtn_cron.log"

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must be run as root. Use sudo." >&2
  exit 1
fi

mkdir -p "$LOG_DIR"
chown www-data:www-data "$LOG_DIR" || true

if [ -f "$CRON_FILE" ]; then
  echo "Backing up existing $CRON_FILE to ${CRON_FILE}.bak"
  cp -a "$CRON_FILE" "${CRON_FILE}.bak"
fi

cat > "$CRON_FILE" <<EOF
# Cron file for bKash RTN processor
# Runs every minute as www-data and logs output
* * * * * www-data php $ROOT_DIR/cron/bkash_rtn_process.php >> $LOG_FILE 2>&1
EOF

chmod 0644 "$CRON_FILE"
chown root:root "$CRON_FILE"

echo "Installed $CRON_FILE"
echo "Logs: $LOG_FILE"
echo "Ensure cron is running: systemctl status cron  (or crond)"

exit 0
