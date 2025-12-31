# $ backup.sh তৈরি করে দিয়েছি এবং এটা config.php থেকে DB তথ্য নিজে পড়বে। এখন চালান:
# chmod +x /var/www/isp_billing/backup.sh
# /var/www/isp_billing/backup.sh

# এটা করবে:

# প্রোজেক্ট ফাইল ব্যাকআপ
# ডাটাবেস ডাম্প
# ইউজার + রুট ক্রনজব ব্যাকআপ
# সব কিছু একটাই .tar.gz আর্কাইভে রাখবে
# যদি অন্য লোকেশনে ব্যাকআপ রাখতে চান, এভাবে চালাতে পারেন:
# BACKUP_ROOT=/var/www/ /var/www/isp_billing/backup.sh

#!/usr/bin/env bash
set -euo pipefail

# ১. প্রাথমিক সেটআপ ও ডিরেক্টরি নির্ধারণ 📂
PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups}"
STAMP="$(date +%F_%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/isp_billing_${STAMP}"
APP_CONFIG="${PROJECT_DIR}/app/config.php"

# ২. ডিরেক্টরি তৈরি নিশ্চিত করা
sudo mkdir -p "${BACKUP_ROOT}"
mkdir -p "${BACKUP_DIR}"

# ৩. ডাটাবেস তথ্য সংগ্রহ করা (PHP Constant থেকে) 🐘
get_php_const() {
  local key="$1"
  php -r "require '$APP_CONFIG'; echo defined('$key') ? constant('$key') : '';"
}

echo "🔍 ডাটাবেস তথ্য সংগ্রহ করা হচ্ছে..."
if [[ -f "${APP_CONFIG}" ]]; then
  DB_HOST=$(get_php_const DB_HOST)
  DB_USER=$(get_php_const DB_USER)
  DB_PASS=$(get_php_const DB_PASS)
  DB_NAME=$(get_php_const DB_NAME)
else
  echo "❌ Error: config.php খুঁজে পাওয়া যায়নি!" >&2
  exit 1
fi

# ৪. ডাটাবেস ডাম্প (নিরাপদ পদ্ধতিতে) 💾
echo "💾 ডাটাবেস ডাম্প করা হচ্ছে: ${DB_NAME}..."
TMP_CNF=$(mktemp)
cat > "${TMP_CNF}" <<CONF
[client]
user=${DB_USER}
password=${DB_PASS}
host=${DB_HOST}
CONF
chmod 600 "${TMP_CNF}"
mysqldump --defaults-extra-file="${TMP_CNF}" --single-transaction --routines --triggers "${DB_NAME}" > "${BACKUP_DIR}/db.sql"
rm -f "${TMP_CNF}"

# ৫. কনফিগারেশন ও SSL ফাইল সংগ্রহ 🔐
echo "📂 সিস্টেম ফাইল সংগ্রহ করা হচ্ছে..."
# ফাইলগুলো থাকলে কপি করো, না থাকলে স্কিপ করো
[ -f "/etc/ssl/certs/isp_billing.crt" ] && sudo cp /etc/ssl/certs/isp_billing.crt "${BACKUP_DIR}/" || echo "⚠️ SSL Cert পাওয়া যায়নি"
[ -f "/etc/ssl/private/isp_billing.key" ] && sudo cp /etc/ssl/private/isp_billing.key "${BACKUP_DIR}/" || echo "⚠️ SSL Key পাওয়া যায়নি"
[ -f "/etc/apache2/sites-available/isp_billing-ssl.conf" ] && sudo cp /etc/apache2/sites-available/isp_billing-ssl.conf "${BACKUP_DIR}/" || echo "⚠️ Apache Conf পাওয়া যায়নি"

# ৬. ক্রন জব ব্যাকআপ ⏰
crontab -l > "${BACKUP_DIR}/cron_user.txt" 2>/dev/null || true
sudo crontab -l > "${BACKUP_DIR}/cron_root.txt" 2>/dev/null || true

# ৭. চূড়ান্ত আর্কাইভ তৈরি (.tar.gz) 📦
BACKUP_TAR="${BACKUP_ROOT}/isp_billing_full_${STAMP}.tar.gz"
echo "📦 আর্কাইভ তৈরি করা হচ্ছে: ${BACKUP_TAR}..."

# প্রোজেক্ট ফাইল এবং অন্যান্য ব্যাকআপ ফাইল একত্রিত করা
tar -czf "${BACKUP_TAR}" -C "$(dirname "${PROJECT_DIR}")" "$(basename "${PROJECT_DIR}")" -C "${BACKUP_DIR}" .

# ৮. গুগল ড্রাইভে আপলোড ও পরিষ্কার করা ☁️
if command -v rclone >/dev/null 2>&1; then
  echo "☁️ গুগল ড্রাইভে আপলোড করা হচ্ছে..."
  rclone copy "${BACKUP_TAR}" gdrive:isp_billing_backup
  echo "✅ আপলোড সম্পন্ন হয়েছে।"
else
  echo "⚠️ rclone খুঁজে পাওয়া যায়নি, ফাইলটি লোকালেই রাখা হলো।"
fi

# অস্থায়ী ফোল্ডার মুছে ফেলা
rm -rf "${BACKUP_DIR}"
echo "✨ ব্যাকআপ সফলভাবে সম্পন্ন হয়েছে!"
