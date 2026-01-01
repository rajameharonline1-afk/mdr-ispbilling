#!/usr/bin/env bash

echo "=========================================="
echo "  📂 ISP Billing এক-ক্লিক মাস্টার সেটআপ 💻  "
echo "=========================================="

# ১. ডিরেক্টরি এবং সিস্টেম আপডেট
echo "🛠️ ডিরেক্টরি তৈরি ও সিস্টেম আপডেট হচ্ছে...📦"
sudo timedatectl set-timezone Asia/Dhaka
sudo mkdir -p /var/www/isp_billing
sudo mkdir -p /var/backups
sudo apt update && sudo apt install -y rclone apache2 mysql-server phpmyadmin

# PHP এবং প্রয়োজনীয় এক্সটেনশন ইনস্টল
sudo apt -y install php libapache2-mod-php php-{gd,common,mysql,pear,db,mbstring,xml,curl,zip,intl,bcmath,imagick}

# MySQL ডেটাবেস ও ইউজার তৈরি
sudo mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS isp_billing DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'isp_user'@'localhost' IDENTIFIED BY 'isp@010230';
ALTER USER 'isp_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'isp@010230';
GRANT ALL PRIVILEGES ON isp_billing.* TO 'isp_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# ২. rclone এবং পাসওয়ার্ড সংগ্রহ
echo -e "\nধাপ ১: rclone ও 🛡️ সিকিউরিটি কনফিগারেশন"
echo "------------------------------------------"
read -p "আপনার rclone টোকেনটি এখানে পেস্ট করুন: " R_TOKEN
read -sp "আপনার MySQL Root 🔑 পাসওয়ার্ড দিন: " DB_PASS
echo -e "\nধন্যবাদ! 🏗️ বাকি কাজ শুরু হচ্ছে..."

# rclone অটো-কনফিগার
mkdir -p ~/.config/rclone
cat <<EOF > ~/.config/rclone/rclone.conf
[gdrive]
type = drive
token = $R_TOKEN
EOF

# ৩. গুগল ড্রাইভ থেকে ব্যাকআপ ডাউনলোড এবং রিস্টোর
echo "☁️ গুগল ড্রাইভ থেকে সর্বশেষ ব্যাকআপ ডাউনলোড হচ্ছে..."
LATEST_BACKUP=$(rclone lsf gdrive:isp_billing_backup --format "t" --sort-modtime | tail -1)

if [ -n "$LATEST_BACKUP" ]; then
    rclone copy "gdrive:isp_billing_backup/$LATEST_BACKUP" /var/backups/
    export BACKUP_ARCHIVE="/var/backups/$LATEST_BACKUP"
    export MYSQL_PWD=$DB_PASS
    # রিস্টোর স্ক্রিপ্ট রান করার আগে নিশ্চিত করুন ফাইলটি আছে
    if [ -f "/var/www/isp_billing/restore.sh" ]; then
        sudo -E bash /var/www/isp_billing/restore.sh
    else
        echo "Warning: restore.sh ফাইলটি পাওয়া যায়নি। ফাইলগুলো ম্যানুয়ালি চেক করুন।"
    fi
else
    echo "Warning: 📋 কোনো ব্যাকআপ ফাইল পাওয়া যায়নি। রিস্টোর স্কিপ করা হচ্ছে।"
fi

# ৪. SSL সার্টিফিকেট সেটআপ
echo "🔐 SSL সার্টিফিকেট সেটআপ হচ্ছে..."
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
-keyout /etc/ssl/private/isp_billing.key \
-out /etc/ssl/certs/isp_billing.crt \
-subj "/C=BD/ST=Dhaka/L=Dhaka/O=ISP Billing/OU=IT Department/CN=isp_billing"

# ৫. Apache কনফিগারেশন
echo "🛠️ ওয়েব সার্ভার কনফিগার করা হচ্ছে..."
sudo a2enmod ssl rewrite

sudo tee /etc/apache2/sites-available/isp_billing.conf > /dev/null <<EOF
<VirtualHost *:443>
    ServerAdmin webmaster@localhost
    ServerName 157.10.243.104
    ServerAlias 172.16.3.10
    DocumentRoot /var/www/isp_billing/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/isp_billing.crt
    SSLCertificateKeyFile /etc/ssl/private/isp_billing.key

    <Directory /var/www/isp_billing/public>
        DirectoryIndex index.php index.html
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    Alias /app /var/www/isp_billing/app
    Alias /api /var/www/isp_billing/api
    Alias /cron /var/www/isp_billing/cron
    Alias /olt /var/www/isp_billing/olt
    Alias /reports /var/www/isp_billing/reports
    Alias /tools /var/www/isp_billing/tools
    Alias /tg /var/www/isp_billing/tg
    Alias /assets /var/www/isp_billing/assets
    Alias /project_root /var/www/isp_billing

    <Directory /var/www/isp_billing>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    RedirectMatch permanent "^/public/(.*)$" "/\$1"
    RedirectMatch permanent "^/$" "/login.php"
    RedirectMatch permanent "^/login/?$" "/login.php"

    ErrorLog \${APACHE_LOG_DIR}/isp_billing-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/isp_billing-ssl-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName 157.10.243.104
    Redirect permanent / https://157.10.243.104/
</VirtualHost>
EOF

sudo a2ensite isp_billing.conf
# phpMyAdmin কনফিগারেশন লিঙ্কিং (যদি ফাইলটি বিদ্যমান থাকে)
if [ -f "/etc/phpmyadmin/apache.conf" ]; then
    sudo ln -sf /etc/phpmyadmin/apache.conf /etc/apache2/conf-enabled/phpmyadmin.conf
fi
sudo systemctl restart apache2

# ৬. Cron Job সেটআপ
echo "⏰ Cron Job শিডিউল করা হচ্ছে...🔍"
cat <<EOF > /tmp/isp_cron
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# OLT & Test Jobs
*/10 * * * * cd /var/www/isp_billing && /usr/bin/php api/olt_mac_refresh_telnet.php
*/5 * * * * echo "\$(date) cron test" >> /var/log/cron_test.log

# Billing & maintenance
10 0 * * * /usr/bin/php /var/www/isp_billing/cron/auto_billing.php >> /var/log/auto_billing.log
*/30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_inactive.php >> /var/log/auto_inactive.log

# Router sync
5 * * * * /usr/bin/php /var/www/isp_billing/cron/sync_clients.php >> /var/log/sync_clients.log

# Notifications & suspensions
5 0 * * * /usr/bin/php /var/www/isp_billing/cron/sms_due_reminder.php >> /var/log/sms_due.log
*/2 * * * * /usr/bin/php /var/www/isp_billing/cron/sms_sender.php >> /var/log/sms_sender.log
*/10 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend_enable.php >> /var/log/suspend_enable.log
*/30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend.php >> /var/log/auto_suspend.log

# Payments & Backup
*/5 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_bkash_apply.php >> /var/log/bkash.log
*/5 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_link_pppoe_olt.php >> /var/log/olt_link.log
0 2 * * * /bin/bash /var/www/isp_billing/backup.sh
EOF

sudo crontab /tmp/isp_cron
rm /tmp/isp_cron

echo "=========================================="
echo "   অভিনন্দন! সেটআপ সফলভাবে সম্পন্ন হয়েছে।   "
echo "=========================================="