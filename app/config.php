<?php
// ===============================
// সিস্টেম কনফিগারেশন ফাইল
// ===============================

// ডাটাবেজ কানেকশন সেটিংস
define('DB_HOST', 'localhost');
define('DB_USER', 'isp_user');       // আপনার MySQL ইউজারনেম
define('DB_PASS', 'isp@010230');           // আপনার MySQL পাসওয়ার্ড
define('DB_NAME', 'isp_billing'); // আপনার ডাটাবেজ নাম
define('SMS_WEBHOOK_SECRET', ''); // REQUIRED
define('SMS_IP_WHITELIST',   ''); // e.g. '103.120.XX.XX, 203.76.XX.XX' (optional)
define('BKASH_MERCHANT_NUMBER', '01303326003'); // আপনার মার্চেন্ট নম্বর
define('BKASH_WEBHOOK_TOKEN', 'AhzD6dT9CV28h24ooWYQe3Q7m98j8Ww5DkxkfLQ8UBg');      // নতুন bKash webhook সিক্রেট
define('BKASH_WEBHOOK_IP_WHITELIST', ''); // কমা-সেপারেটেড আইপি (optional)


// /app/config.php
define('TELEGRAM_BOT_TOKEN', ''); // <-- BotFather থেকে পাওয়া Token
define('TELEGRAM_CHAT_ID',  '');    // <-- আপনার user/group/channel chat_id



// সেশন লাইফটাইম (১ ঘণ্টা)
define('SESSION_LIFETIME', 3600);

// টাইমজোন সেট করুন
date_default_timezone_set('Asia/Dhaka');

// এরর রিপোর্টিং
error_reporting(E_ALL);
ini_set('display_errors', 1);
