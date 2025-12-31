# স্ক্রিপ্টটিতে পারমিশন দিন:
# chmod +x /var/www/isp_billing/restore.sh
#!/usr/bin/env bash
set -euo pipefail

echo "=========================================="
echo "    📂 ISP Billing রিস্টোর 💻      "
echo "=========================================="

# ১. সর্বশেষ ব্যাকআপ খুঁজে বের করা 🔍
LATEST_TAR=$(find /var/backups -name "isp_billing_full_*.tar.gz" -printf "%T@ %p\n" | sort -n | tail -1 | awk '{print $2}')

if [[ -z "${LATEST_TAR}" ]]; then
  echo "❌ Error: কোনো ব্যাকআপ ফাইল পাওয়া যায়নি!"
  exit 1
fi

echo "✅ রিস্টোর করা হচ্ছে: ${LATEST_TAR}"

# ২. পাসওয়ার্ড সংগ্রহ 🔑
read -sp "MySQL Root পাসওয়ার্ড দিন: " DB_PASS
echo -e "\n"

# ৩. টেম্পোরারি ফোল্ডারে ফাইল এক্সট্রাক্ট করা 📦
TEMP_DIR="/tmp/isp_restore_$(date +%s)"
mkdir -p "${TEMP_DIR}"
echo "📦 ফাইল আনজিপ করা হচ্ছে..."
sudo tar -xzf "${LATEST_TAR}" -C "${TEMP_DIR}"

# ৪. সিস্টেম কনফিগারেশন রিস্টোর করা 🛠️
echo "⚙️ Apache এবং SSL কনফিগারেশন রিস্টোর হচ্ছে..."
sudo cp "${TEMP_DIR}/isp_billing-ssl.conf" /etc/apache2/sites-available/
sudo cp "${TEMP_DIR}/isp_billing.crt" /etc/ssl/certs/
sudo cp "${TEMP_DIR}/isp_billing.key" /etc/ssl/private/

# ৫. প্রজেক্ট ফাইল রিস্টোর 📂
echo "📁 প্রজেক্ট ফাইল আপডেট করা হচ্ছে..."
sudo mkdir -p /var/www/isp_billing
sudo cp -r "${TEMP_DIR}/isp_billing/." /var/www/isp_billing/

# ৬. ডাটাবেস রিস্টোর 💾
echo "💾 ডাটাবেস ইমপোর্ট হচ্ছে..."
export MYSQL_PWD="${DB_PASS}"
mysql -u root -e "DROP DATABASE IF EXISTS isp_billing; CREATE DATABASE isp_billing;"
mysql -u root isp_billing < "${TEMP_DIR}/db.sql"

# ৭. Apache সাইট সক্রিয় করা ও রিস্টার্ট 🔄
echo "🔄 Apache সেটআপ সম্পন্ন হচ্ছে..."
sudo a2enmod ssl rewrite > /dev/null
sudo a2ensite isp_billing-ssl.conf > /dev/null
sudo systemctl restart apache2

# পরিষ্কার করা 🧹
sudo rm -rf "${TEMP_DIR}"
unset MYSQL_PWD

echo "=========================================="
echo "   ✨ অভিনন্দন! রিস্টোর সফল হয়েছে। ✨     "
echo "=========================================="