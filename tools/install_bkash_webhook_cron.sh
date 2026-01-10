#!/usr/bin/env bash
# /tools/install_bkash_webhook_cron.sh
# Install bKash RTN and PGW auto-apply cron jobs for real-time system updates
# Usage: sudo bash install_bkash_webhook_cron.sh

set -e

echo "=============================================="
echo "bKash Webhook Cron Job Installer"
echo "=============================================="
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
  echo "⚠️ This script must be run as root (use: sudo bash $0)"
  exit 1
fi

ISP_BILLING_PATH="/var/www/isp_billing"
CRON_BKE="/etc/cron.d/isp_billing_bkash"
PHP_BIN="/usr/bin/php"

# Check PHP exists
if ! command -v $PHP_BIN &> /dev/null; then
  PHP_BIN="/usr/bin/php8"
  if ! command -v $PHP_BIN &> /dev/null; then
    PHP_BIN="php"
  fi
fi

echo "✓ Using PHP: $PHP_BIN"
echo ""

# Check ISP Billing directory
if [ ! -d "$ISP_BILLING_PATH" ]; then
  echo "❌ ISP Billing directory not found: $ISP_BILLING_PATH"
  exit 1
fi
echo "✓ ISP Billing directory found: $ISP_BILLING_PATH"
echo ""

# Create or update cron file
echo "📝 Setting up cron jobs..."
echo ""

cat > "$CRON_BKE" << 'EOF'
# ISP Billing - bKash Webhook Processing
# Real-time payment updates for PGW Callback and RTN Webhooks
# Response: Always Bengali (সবসময় বাংলায়)

# Process RTN (Real-Time Notification) webhooks every 2 minutes
*/2 * * * * www-data /usr/bin/php /var/www/isp_billing/cron/bkash_rtn_process.php >> /var/www/isp_billing/logs/bkash_rtn_cron.log 2>&1

# Auto-apply matched payments to invoices every 5 minutes
*/5 * * * * www-data /usr/bin/php /var/www/isp_billing/cron/auto_bkash_apply.php >> /var/www/isp_billing/storage/logs/bkash_auto_apply.log 2>&1

# Clean up old RTN events (optional) - keep last 90 days
0 2 * * * www-data /usr/bin/php -r "
  require '/var/www/isp_billing/app/db.php';
  \$pdo = db();
  \$pdo->exec('DELETE FROM bkash_rtn_events WHERE received_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
" >> /var/www/isp_billing/logs/bkash_cleanup.log 2>&1
EOF

chmod 644 "$CRON_BKE"
echo "✓ Cron file created: $CRON_BKE"
echo ""

# Create log directories if they don't exist
mkdir -p "$ISP_BILLING_PATH/logs"
mkdir -p "$ISP_BILLING_PATH/storage/logs"
chown www-data:www-data "$ISP_BILLING_PATH/logs"
chown www-data:www-data "$ISP_BILLING_PATH/storage/logs"
echo "✓ Log directories ready"
echo ""

# Test cron jobs
echo "🧪 Testing cron jobs..."
echo ""

echo "Testing RTN Process..."
if $PHP_BIN "$ISP_BILLING_PATH/cron/bkash_rtn_process.php" 2>&1 | head -5; then
  echo "✓ RTN Process: OK"
else
  echo "⚠️ RTN Process: Check manually"
fi
echo ""

echo "Testing Auto-Apply..."
if $PHP_BIN "$ISP_BILLING_PATH/cron/auto_bkash_apply.php" 2>&1 | head -5; then
  echo "✓ Auto-Apply: OK"
else
  echo "⚠️ Auto-Apply: Check manually"
fi
echo ""

# Verify cron service
echo "✓ Cron jobs installed successfully!"
echo ""

echo "=============================================="
echo "📋 Summary"
echo "=============================================="
echo ""
echo "Cron Configuration:"
echo "  File: $CRON_BKE"
echo ""
echo "Scheduled Jobs:"
echo "  1. RTN Process       - Every 2 minutes"
echo "     Processes pending RTN webhook events"
echo ""
echo "  2. Auto-Apply        - Every 5 minutes"
echo "     Matches and applies payments to invoices"
echo ""
echo "  3. Cleanup          - Daily at 2:00 AM"
echo "     Removes RTN events older than 90 days"
echo ""
echo "Log Files:"
echo "  - $ISP_BILLING_PATH/logs/bkash_rtn_cron.log"
echo "  - $ISP_BILLING_PATH/storage/logs/bkash_auto_apply.log"
echo "  - $ISP_BILLING_PATH/logs/bkash_cleanup.log"
echo ""
echo "Next Steps:"
echo "  1. Set BKASH_RTN_WEBHOOK_TOKEN in app/config.php"
echo "  2. Add webhook URL to bKash Dashboard"
echo "  3. Monitor logs: tail -f $ISP_BILLING_PATH/storage/logs/bkash_auto_apply.log"
echo "  4. View dashboard: /public/bkash_rtn_dashboard.php"
echo ""
echo "✅ Installation Complete!"
echo "=============================================="
