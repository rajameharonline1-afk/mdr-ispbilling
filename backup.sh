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

PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups}"
STAMP="$(date +%F_%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/isp_billing_${STAMP}"
APP_CONFIG="${PROJECT_DIR}/app/config.php"
APACHE_DIR="/etc/apache2"
SSL_DIR="/etc/ssl"
LE_DIR="/etc/letsencrypt"

if ! command -v tar >/dev/null 2>&1; then
  echo "Error: tar not found." >&2
  exit 1
fi

MYSQLDUMP_BIN="$(command -v mysqldump || true)"
if [[ -z "${MYSQLDUMP_BIN}" ]]; then
  echo "Error: mysqldump not found." >&2
  exit 1
fi

get_php_const() {
  local key="$1"
  php -r "require '$APP_CONFIG'; echo defined('$key') ? constant('$key') : '';"
}

if command -v php >/dev/null 2>&1 && [[ -f "${APP_CONFIG}" ]]; then
  DB_HOST="${DB_HOST:-$(get_php_const DB_HOST)}"
  DB_USER="${DB_USER:-$(get_php_const DB_USER)}"
  DB_PASS="${DB_PASS:-$(get_php_const DB_PASS)}"
  DB_NAME="${DB_NAME:-$(get_php_const DB_NAME)}"
else
  DB_HOST="${DB_HOST:-}"
  DB_USER="${DB_USER:-}"
  DB_PASS="${DB_PASS:-}"
  DB_NAME="${DB_NAME:-}"
fi

if [[ -z "${DB_NAME}" || -z "${DB_USER}" || -z "${DB_HOST}" ]]; then
  echo "Error: DB settings missing. Set DB_HOST/DB_USER/DB_PASS/DB_NAME or ensure app/config.php exists." >&2
  exit 1
fi

mkdir -p "${BACKUP_DIR}"

echo "Backing up project: ${PROJECT_DIR}"
echo "Backup directory: ${BACKUP_DIR}"

echo "Dumping database: ${DB_NAME}"
TMP_CNF="$(mktemp)"
cat > "${TMP_CNF}" <<CONF
[client]
user=${DB_USER}
password=${DB_PASS}
host=${DB_HOST}
CONF
chmod 600 "${TMP_CNF}"
"${MYSQLDUMP_BIN}" --defaults-extra-file="${TMP_CNF}" \
  --single-transaction --routines --events --triggers \
  "${DB_NAME}" > "${BACKUP_DIR}/db.sql"
rm -f "${TMP_CNF}"

crontab -l > "${BACKUP_DIR}/cron_user.txt" 2>/dev/null || true
if [[ ${EUID} -eq 0 ]]; then
  crontab -l > "${BACKUP_DIR}/cron_root.txt" 2>/dev/null || true
  cp -a /etc/crontab /etc/cron.d /etc/cron.daily /etc/cron.hourly /etc/cron.weekly /etc/cron.monthly \
    "${BACKUP_DIR}/" 2>/dev/null || true

  if [[ -d "${APACHE_DIR}" ]]; then
    cp -a "${APACHE_DIR}" "${BACKUP_DIR}/" 2>/dev/null || true
  fi
  if [[ -d "${SSL_DIR}" ]]; then
    cp -a "${SSL_DIR}" "${BACKUP_DIR}/" 2>/dev/null || true
  fi
  if [[ -d "${LE_DIR}" ]]; then
    cp -a "${LE_DIR}" "${BACKUP_DIR}/" 2>/dev/null || true
  fi
else
  sudo crontab -l -u root > "${BACKUP_DIR}/cron_root.txt" 2>/dev/null || true
  sudo cp -a /etc/crontab /etc/cron.d /etc/cron.daily /etc/cron.hourly /etc/cron.weekly /etc/cron.monthly \
    "${BACKUP_DIR}/" 2>/dev/null || true

  if [[ -d "${APACHE_DIR}" ]]; then
    sudo cp -a "${APACHE_DIR}" "${BACKUP_DIR}/" 2>/dev/null || true
  fi
  if [[ -d "${SSL_DIR}" ]]; then
    sudo cp -a "${SSL_DIR}" "${BACKUP_DIR}/" 2>/dev/null || true
  fi
  if [[ -d "${LE_DIR}" ]]; then
    sudo cp -a "${LE_DIR}" "${BACKUP_DIR}/" 2>/dev/null || true
  fi
fi

BACKUP_TAR="${BACKUP_DIR}/isp_billing_full_${STAMP}.tar.gz"
PARENT_DIR="$(dirname "${PROJECT_DIR}")"
PROJECT_NAME="$(basename "${PROJECT_DIR}")"

tar -czf "${BACKUP_TAR}" \
  -C "${PARENT_DIR}" "${PROJECT_NAME}" \
  -C "${BACKUP_DIR}" db.sql cron_user.txt cron_root.txt crontab cron.d cron.daily cron.hourly cron.weekly cron.monthly \
  apache2 ssl letsencrypt

echo "Backup complete: ${BACKUP_TAR}"
