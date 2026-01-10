# ISP Billing & Management System - Complete Guide

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture & Technology Stack](#architecture--technology-stack)
3. [Database Schema](#database-schema)
4. [Core Modules](#core-modules)
5. [API Endpoints](#api-endpoints)
6. [User Interfaces](#user-interfaces)
7. [Installation & Setup](#installation--setup)
8. [Configuration](#configuration)
9. [Usage Guide](#usage-guide)
10. [Maintenance & Monitoring](#maintenance--monitoring)
11. [Troubleshooting](#troubleshooting)
12. [Security Considerations](#security-considerations)
13. [Future Enhancements](#future-enhancements)
14. [Support & Documentation](#support--documentation)

## System Overview

This is a comprehensive ISP (Internet Service Provider) billing and management system built with PHP and MySQL. The system provides complete functionality for managing clients, billing, payments, OLT (Optical Line Terminal) operations, and network monitoring.

### Key Features
- **Client Management**: Complete client lifecycle management with PPPoE integration
- **Billing System**: Automated invoice generation, payment tracking, and financial reporting
- **Network Management**: OLT integration, ONU monitoring, and network diagnostics
- **Router Integration**: MikroTik RouterOS API integration for client control
- **Dual Portal System**: Separate admin and client portals
- **Financial Management**: Income/expense tracking, wallet system, and reporting
- **Employee Management**: HR module with attendance tracking
- **Audit System**: Complete activity logging and audit trails
Complete the technology by creating complete files and folders

## Architecture & Technology Stack

### Backend
- **PHP 8.0+** with PDO for database operations
- **MySQL/MariaDB** database with InnoDB engine
- **Composer** for dependency management
- **PHPMailer** for email functionality
- **DomPDF** for PDF generation

### Frontend
- **Bootstrap 5.3.3** for responsive UI
- **Bootstrap Icons** for iconography
- **Custom CSS** with modern glass-morphism design
- **JavaScript** for interactive features

### Network Integration
- **MikroTik RouterOS API** for router management
- **Telnet/SSH** for OLT communication
- **SNMP** for network monitoring

### File Structure
```
/var/www/isp_billing
├── api/           # API endpoints
├── app/           # Core application logic (auth, billing, helpers, mikrotik, OLT)
├── assets/        # CSS/JS/images
├── backups/       # DB backups (.sql)
├── cron/          # Scheduled tasks (auto_billing, olt_poll, sync_clients, sms...)
├── olt/           # OLT management files/commands
├── partials/      # Reusable UI components
├── private/       # Private assets/config (keep non-public)
├── public/        # Web root (admin + portal)
│   ├── admin/     # Admin pages
│   └── portal/    # Client portal
├── reports/       # Report generation
├── storage/       # Logs/temp (`storage/logs/`)
├── tests/         # PHPUnit tests
├── tg/            # Telegram utilities
├── tools/         # Utility scripts (phpunit.sh, log_watch.sh, logrotate config)
├── uploads/       # Uploaded files
├── vendor/        # Composer dependencies
├── isp_billing.sql / db.sql  # Schema/dump
└── ISP_Billing_System_Guide.md (this guide)
```
- দ্রুত চেক: `cd /var/www/isp_billing && ls`
- ডিরেক্টরি ট্রি (সারাংশ): `find /var/www/isp_billing -maxdepth 2 -type d`
- লগ ডিরেক্টরি: `storage/logs/`

## Database Schema

### Core Tables

#### 1. Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','manager','branch_manager') DEFAULT 'admin',
    status TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### 2. Clients Table
```sql
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    pppoe_id VARCHAR(50) UNIQUE NOT NULL,
    pppoe_password VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    package_id INT NOT NULL,
    router_id INT DEFAULT NULL,
    olt_id INT DEFAULT NULL,
    status ENUM('active','inactive','expired','pending','left') DEFAULT 'pending',
    ledger_balance DECIMAL(14,2) DEFAULT 0.00,
    join_date DATE NOT NULL,
    expire_date DATE DEFAULT NULL,
    is_online TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    -- Additional fields for OLT integration
    onu_mac VARCHAR(100) DEFAULT NULL,
    onu_model VARCHAR(80) DEFAULT NULL,
    connection_type VARCHAR(100) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 3. Packages Table
```sql
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    speed VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    validity INT NOT NULL,
    description TEXT DEFAULT NULL
);
```

#### 4. Invoices Table
```sql
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    vat_percent DECIMAL(5,2) DEFAULT 0.00,
    vat_amount DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
    package_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 5. Payments Table
```sql
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method ENUM('cash','bank','bkash','nagad','online') NOT NULL,
    received_by INT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Additional Tables
- **routers**: MikroTik router configurations
- **olts**: OLT device management
- **olt_data**: ONU monitoring data
- **packages**: Service packages
- **employees**: HR management
- **attendance**: Employee attendance
- **income**: Income tracking
- **expenses**: Expense management
- **accounts**: Financial accounts
- **audit_logs**: System audit trail
- **client_ledger**: Client balance tracking
- **client_traffic_log**: Traffic monitoring

## Core Modules

### 1. Authentication & Authorization (`app/auth.php`)
- MD5 password hashing (consider upgrading to bcrypt)
- Session management
- Role-based access control
- Login/logout functionality

### 2. Database Layer (`app/db.php`)
- PDO-based database connection
- Singleton pattern for connection reuse
- Error handling and exception management

### 3. MikroTik Integration (`app/mikrotik.php`)
- RouterOS API integration
- PPPoE user management
- Real-time connection monitoring
- Bulk client control operations

### 4. OLT Management (`app/olt_telnet.php`)
- Telnet/SSH communication with OLT devices
- ONU monitoring and diagnostics
- Optical power level checking
- Command execution and response parsing

### 5. Billing System (`app/billing_helpers.php`)
- Invoice calculation with VAT support
- Payment processing
- Ledger balance management
- Automated billing workflows

### 6. Helper Functions (`app/helpers.php`)
- Logging system
- SMS/Email notifications (placeholder implementations)
- MAC address normalization
- File and directory utilities

## API Endpoints

### Client Management APIs
- `GET /api/client_status.php` - Get client connection status
- `POST /api/client_change_package.php` - Change client package
- `POST /api/client_restore.php` - Restore suspended client
- `POST /api/client_left_toggle.php` - Toggle client left status

### OLT Management APIs
- `POST /api/olt_run.php` - Execute OLT commands
- `GET /api/onu_power.php` - Get ONU optical power levels
- `POST /api/onu_action.php` - Perform ONU actions (reboot, diagnostics)
- `GET /api/pon_scan.php` - Scan PON ports

### Bulk Operations APIs
- `POST /api/bulk_control.php` - Bulk enable/disable clients
- `POST /api/bulk_notify.php` - Send bulk notifications
- `POST /api/bulk_profile.php` - Bulk profile management

### Billing APIs
- `POST /api/invoice_create.php` - Create new invoice
- `POST /api/invoice_mark_paid.php` - Mark invoice as paid
- `POST /api/payment_mark_paid.php` - Process payment
- `GET /api/get_package_price.php` - Get package pricing

### Network Monitoring APIs
- `GET /api/client_live_status.php` - Real-time client status
- `GET /api/traffic_graph.php` - Traffic usage graphs
- `GET /api/olt_mac_refresh.php` - Refresh OLT MAC tables

## User Interfaces

### Admin Portal (`/public/`)

#### Dashboard (`/public/index.php`)
- System overview with KPIs
- Client statistics (total, online, offline)
- Financial summary
- Quick action buttons
- Recent activity feed

#### Client Management (`/public/clients.php`)
- Client listing with advanced filtering
- Search functionality
- Status management
- Bulk operations
- Export capabilities

#### Billing System (`/public/billing.php`)
- Month-wise billing overview
- Invoice management
- Payment processing
- Due date tracking
- Financial reports

#### Network Management
- **OLT Management** (`/public/olts.php`): OLT device configuration
- **Router Management** (`/public/routers.php`): MikroTik router setup
- **ONU Monitoring** (`/public/onu_monitor.php`): Real-time ONU status

#### Financial Management
- **Income/Expense** (`/public/income_expense.php`): Financial tracking
- **Wallets** (`/public/wallets.php`): Digital wallet system
- **Reports** (`/public/reports/`): Various financial reports

### Client Portal (`/public/portal/`)

#### Client Dashboard (`/public/portal/index.php`)
- Personal account overview
- Service status
- Ledger balance
- Quick actions

#### Billing & Payments
- **Invoices** (`/public/portal/invoices.php`): View invoices
- **Payments** (`/public/portal/payments.php`): Payment history
- **Billing Overview** (`/public/portal/billing_overview.php`): Account summary

#### Support
- **Tickets** (`/public/portal/tickets.php`): Support ticket system
- **Profile** (`/public/portal/profile.php`): Account settings


## Installation & Setup

### Prerequisites
- PHP 8.0+ with extensions: `pdo_mysql`, `openssl`, `mbstring`, `xml`, `curl`, `zip`, `gd`
- MySQL/MariaDB server (InnoDB enabled)
- Composer for dependency management
- Apache/Nginx serving the `public/` directory as the document root
- Network tools available on the host: telnet/ssh clients and RouterOS API reachability

### Step-by-Step Setup
1. **Clone/Copy Codebase**
   ```bash
   # উদাহরণ: নিজস্ব Git সার্ভার/URL দিন
   git clone https://your-git.example.com/isp_billing.git /var/www/isp_billing
   cd /var/www/isp_billing
   ```
   - নিজের রিপো URL ব্যবহার করুন; উদাহরণ ডোমেইন `your-git.example.com` শুধু প্লেসহোল্ডার।
2. **Install PHP & System Tools (Ubuntu/Debian উদাহরণ)**
   ```bash
   sudo apt update
   sudo apt install -y php php-cli php-curl php-mbstring php-xml php-zip php-gd php-mysql php-bcmath php-snmp php-ssh2 \
       curl unzip git net-tools telnet snmp
   sudo apt install -y composer   # Composer না থাকলে
   # Apache ব্যবহার করলে:
   sudo apt install -y apache2 libapache2-mod-php
   sudo a2enmod rewrite && sudo systemctl restart apache2
   # Nginx + PHP-FPM হলে উপযুক্ত php-fpm প্যাকেজ ইন্সটল ও কনফিগ করুন
   ```
3. **Install PHP Dependencies**
   ```bash
   composer install --no-dev
   ```
4. **Create Database**
   ```bash
   mysql -u root -p -e "CREATE DATABASE isp_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
5. **Import Schema & Seed Data**
   ```bash
   mysql -u root -p isp_billing < isp_billing.sql
   ```
   (Optional) Restore from a backup in `backups/` instead of `isp_billing.sql`.
   - ব্যাকআপ থেকে রিস্টোর উদাহরণ: `mysql -u root -p isp_billing < backups/isp_billing_2025-08-01.sql`
   - নতুন মেশিনে ডাম্প নিতে: `mysqldump -u root -p isp_billing > backups/isp_billing_$(date +%F).sql`
6. **Configure Application**
   - Update `app/config.php` with DB credentials, timezone, and secrets (SMS webhook secret, Telegram bot token/chat ID).
   - Review router/OLT connection details in relevant scripts under `app/`, `api/`, and `olt/`.
7. **Set File Permissions**
   ```bash
   chown -R www-data:www-data storage uploads backups
   chmod -R 755 storage uploads backups
   chmod 644 app/config.php
   ```
8. **Web Server Virtual Host (Apache example)**
   ```
   <VirtualHost *:80>
       ServerName isp-billing.local
       DocumentRoot /var/www/isp_billing/public
       <Directory /var/www/isp_billing/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
   Enable the site, ensure `mod_rewrite` is on, then reload Apache.
   - পূর্ণ কনফিগ উদাহরণ (Alias + rewrite fallback): `Apaci2-config.txt` এর কনটেন্ট `/etc/apache2/sites-available/isp_billing.conf`-এ কপি করুন।
   - দ্রুত কমান্ড:
     - কনফিগ এডিট: `sudo nano /etc/apache2/sites-available/isp_billing.conf`
     - মড রিরাইট অন: `sudo a2enmod rewrite`
     - সাইট অন: `sudo a2ensite isp_billing.conf`
     - ডিফল্ট সাইট অফ (ঐচ্ছিক): `sudo a2dissite 000-default.conf`
     - কনফিগ টেস্ট: `sudo apache2ctl configtest`
     - রিলোড: `sudo systemctl reload apache2`
   - এই কনফিগে `DocumentRoot /var/www/isp_billing/public` কিন্তু Alias দিয়ে `/app`, `/api`, `/cron`, `/olt`, `/reports`, `/tools`, `/tg`, `/assets`, `/project_root` এক্সপোজ করা আছে; রিরাইট রুল পুরনো `/public/...` URL-কে রুটে পাঠায় এবং রুট `/` কে `/login.php` তে রিডাইরেক্ট করে।
   - পূর্ণ Alias কনফিগ (কপি-পেস্ট রেডি):
     ```
     <VirtualHost *:80>
         ServerAdmin webmaster@localhost
         DocumentRoot /var/www/isp_billing/public
         ServerName 172.16.3.3

         <Directory /var/www/isp_billing/public>
             DirectoryIndex index.php index.html
             Options FollowSymLinks
             AllowOverride All
             Require all granted
         </Directory>

         Alias /app     /var/www/isp_billing/app
         Alias /api     /var/www/isp_billing/api
         Alias /cron    /var/www/isp_billing/cron
         Alias /olt     /var/www/isp_billing/olt
         Alias /reports /var/www/isp_billing/reports
         Alias /tools   /var/www/isp_billing/tools
         Alias /tg      /var/www/isp_billing/tg
         Alias /assets  /var/www/isp_billing/assets
         Alias /project_root /var/www/isp_billing

         <Directory /var/www/isp_billing/app>      AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/api>      AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/cron>     AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/olt>      AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/reports>  AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/tools>    AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/tg>       AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing/assets>   AllowOverride All Require all granted </Directory>
         <Directory /var/www/isp_billing>          Options FollowSymLinks AllowOverride All Require all granted </Directory>

         RedirectMatch permanent "^/public/(.*)$" "/$1"
         RedirectMatch permanent "^/$" "/login.php"
         RedirectMatch permanent "^/login/?$" "/login.php"

         RewriteEngine On
         RewriteCond %{REQUEST_FILENAME} !-f
         RewriteCond %{REQUEST_FILENAME} !-d
         RewriteCond %{DOCUMENT_ROOT}/$1.php -f
         RewriteRule ^(.+)$ /$1.php [L,QSA]
         RewriteCond %{REQUEST_URI} !^/phpmyadmin
         RewriteCond %{REQUEST_URI} !^/zabbix
         RewriteCond %{REQUEST_URI} !^/login.php
         RewriteCond %{REQUEST_URI} !^/project_root/
         RewriteCond %{DOCUMENT_ROOT}/$1 !-f
         RewriteCond %{DOCUMENT_ROOT}/$1 !-d
         RewriteRule ^(.*)$ /project_root/$1 [L,PT]

         ErrorLog ${APACHE_LOG_DIR}/isp_billing-error.log
         CustomLog ${APACHE_LOG_DIR}/isp_billing-access.log combined
     </VirtualHost>
     ```
   - Nginx (PHP-FPM) উদাহরণ:
     ```nginx
     server {
         listen 80;
         server_name isp-billing.local;
         root /var/www/isp_billing/public;
         index index.php index.html;

         location / {
             try_files $uri $uri/ /index.php?$args;
         }

         location ~ \.php$ {
             include snippets/fastcgi-php.conf;
             fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # আপনার PHP-FPM ভার্সন দিন
         }

         client_max_body_size 20m;
         access_log /var/log/nginx/isp_billing_access.log;
         error_log  /var/log/nginx/isp_billing_error.log;
     }
     ```
     - দ্রুত কমান্ড: `sudo nano /etc/nginx/sites-available/isp_billing`, তারপর `sudo ln -s /etc/nginx/sites-available/isp_billing /etc/nginx/sites-enabled/`; টেস্ট `sudo nginx -t`; রিলোড `sudo systemctl reload nginx`.
9. **Cron Jobs**
   Add the automation tasks (adjust PHP path/user):
   ```
   */30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_billing.php >> /var/log/isp_billing/cron.log 2>&1
   */10 * * * * /usr/bin/php /var/www/isp_billing/cron/olt_poll.php >> /var/log/isp_billing/cron.log 2>&1
   1 2 * * *   /usr/bin/php /var/www/isp_billing/cron/db_backup.php >> /var/log/isp_billing/backup.log 2>&1
   */30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_inactive.php >> /var/log/isp_billing/cron.log 2>&1
   ```
10. **Access the Application**
   - Visit `http://isp-billing.local/` (admin) or `http://isp-billing.local/portal/` (client).
   - If no seed admin exists, create one directly in the `users` table.
   - দ্রুত অ্যাডমিন তৈরি (উদাহরণ SQL — MD5 হ্যাশ, পরে bcrypt-এ আপগ্রেড করুন):
     ```sql
     INSERT INTO users (username, password, full_name, role, status) 
     VALUES ('admin', MD5('strongpass'), 'Super Admin', 'admin', 1);
     ```
   - প্রয়োজনে CLI থেকে চালান: `mysql -u root -p isp_billing -e "INSERT ..."`


## Configuration
### Database Configuration (`app/config.php`)
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Zabbix@010230');
define('DB_NAME', 'isp_billing');
define('SESSION_LIFETIME', 3600);
date_default_timezone_set('Asia/Dhaka');
```
- ন্যূনতম প্রিভিলেজ DB ইউজার তৈরি (সিকিউরিটি বেস্ট প্র্যাকটিস):
  ```bash
  mysql -u root -p -e "CREATE USER 'isp_billing'@'localhost' IDENTIFIED BY 'StrongPass!23'; GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX ON isp_billing.* TO 'isp_billing'@'localhost'; FLUSH PRIVILEGES;"
  ```
  তারপর `app/config.php`-এ `DB_USER`, `DB_PASS` আপডেট করুন।

### Email (SMTP) Configuration (`app/mailer.php`)
- পরিবেশ ভেরিয়েবল সেট করুন (Apache-এ `/etc/apache2/envvars` বা সার্ভিস ইউনিটে, অথবা শেল থেকে এক্সপোর্ট):
  ```bash
  export MAIL_FROM='no-reply@yourdomain.com'
  export MAIL_FROM_NAME='ISP Billing'
  export SMTP_HOST='smtp.yourdomain.com'
  export SMTP_PORT=587        # বা 465 (ssl)
  export SMTP_USER='smtp_user'
  export SMTP_PASS='StrongSMTPpass'
  export SMTP_SECURE='tls'    # tls বা ssl
  export SMTP_ALLOW_SELF_SIGNED=0
  export MAIL_DEBUG=0         # 0..4 (2=verbose)
  ```
- টেস্ট (CLI): `php -r "require 'app/mailer.php'; var_dump(send_mail('you@yourdomain.com','Test','<b>Hi</b>'));"`
- ব্যর্থ হলে Apache/PHP এররলগে PHPMailer ডিবাগ দেখুন (`MAIL_DEBUG=2` দিলে বেশি আউটপুট)।
- `yourdomain.com`/`smtp_user`/পাসওয়ার্ড সব জায়গায় নিজের বাস্তব ডোমেইন/অ্যাকাউন্ট বসান।

### SMS Gateway Configuration (`app/helpers.php` > `send_sms`)
- এখানে প্লেসহোল্ডার `send_sms` আছে; নিজের গেটওয়ে API বসান।
- উদাহরণ (cURL POST) স্কেচ:
  ```php
  function send_sms($to, $text){
      $resp = @file_get_contents('https://sms-gateway.example.com/api/send?to='.urlencode($to).'&msg='.urlencode($text));
      log_msg("SMS to {$to}: {$text} | resp={$resp}", 'alerts.log');
      return (bool)$resp;
   }
   ```
- ক্রন (সাজেশন) আগে থেকে: `sms_due_reminder.php` ও `sms_sender.php` লগ `storage/logs/sms.log` এ যায়; প্রোডে গেটওয়ে কোড বসিয়ে রান নিশ্চিত করুন।
- `sms-gateway.example.com` জায়গায় নিজের গেটওয়ে এন্ডপয়েন্ট/টোকেন ব্যবহার করুন; সিক্রেট env ভেরিয়েবল হিসেবে রাখুন।

### Telegram Alert Configuration (`app/telegram.php`)
- প্রয়োজনীয় ভেরিয়েবল (env বা config): `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`.
- শেল এক্সপোর্ট উদাহরণ:
  ```bash
  export TELEGRAM_BOT_TOKEN="123456:ABCDEF_your_bot_token"
  export TELEGRAM_CHAT_ID="-1001234567890"   # গ্রুপ হলে নেগেটিভ আইডি
  ```
- টেস্ট (CLI):
  ```bash
  php -r "require 'app/telegram.php'; echo telegram_send('Test from CLI') ? 'OK' : 'FAIL';"
  ```
- ব্যর্থ হলে লগে/আউটপুটে Telegram API রেসপন্স দেখুন; ফায়ারওয়ালে `api.telegram.org` আউটবাউন্ড খোলা থাকতে হবে।

## Usage Guide

### Admin Operations

#### 1. Adding New Clients
1. Navigate to **Clients** → **Add Client**
2. Fill in client details:
   - Client Code (unique identifier)
   - Name and contact information
   - PPPoE credentials
   - Package selection
   - Router assignment
3. Save and activate the client

#### 2. Managing Billing
1. **Generate Invoices**:
   - Go to **Billing** → **Generate Invoices**
   - Select billing month
   - Choose clients or generate for all
2. **Process Payments**:
   - Navigate to **Payments** → **Add Payment**
   - Select invoice and payment method
   - Record payment details

#### 3. Network Management
1. **OLT Operations**:
   - Go to **OLT** → **OLT Management**
   - Select OLT device
   - Execute commands or monitor ONUs
2. **Router Control**:
   - Navigate to **Routers** → **Router Management**
   - Enable/disable clients
   - Monitor active sessions

#### 4. Monitoring & Reports
1. **Client Status**: Real-time online/offline status
2. **Traffic Monitoring**: Bandwidth usage graphs
3. **Financial Reports**: Income, expenses, and profitability
4. **Audit Logs**: System activity tracking

### Client Portal Operations

#### 1. Account Overview
- View service status and package details
- Check ledger balance
- Monitor connection status

#### 2. Billing & Payments
- View invoices and payment history
- Download invoice PDFs
- Check due dates and amounts

#### 3. Support
- Create support tickets
- View ticket status
- Update profile information

## Maintenance & Monitoring

### Scheduled Tasks (`cron/`)

#### 1. Automated Billing (`cron/auto_billing.php`)
- Generates monthly invoices
- Sends payment reminders
- Updates client statuses
- কমান্ড/পাথ:
  - ওয়েব ড্রাই রান: `http://localhost/cron/auto_billing.php?dry=1`
  - ওয়েব ব্যাকফিল (নির্দিষ্ট মাস): `http://localhost/cron/auto_billing.php?month=YYYY-MM&force=1`
  - CLI ব্যাকফিল: `php /var/www/isp_billing/cron/auto_billing.php 2025-09 --force`
  - দৈনিক ক্রন (BD 00:10 উদাহরণ): `10 0 * * * /usr/bin/php /var/www/isp_billing/cron/auto_billing.php >> /var/www/isp_billing/storage/logs/auto_billing.log 2>&1`

#### 2. Network Monitoring (`cron/olt_poll.php`)
- Polls OLT devices for status
- Updates ONU information
- Monitors optical power levels
- ম্যানুয়াল চালানো: `php /var/www/isp_billing/cron/olt_poll.php`
- ক্রন উদাহরণ (প্রতি 10 মিনিট): `*/10 * * * * /usr/bin/php /var/www/isp_billing/cron/olt_poll.php >> /var/www/isp_billing/storage/logs/olt_poll.log 2>&1`

#### 3. Database Maintenance (`cron/db_backup.php`)
- Automated database backups
- Log rotation
- Cleanup old data
- ম্যানুয়াল ব্যাকআপ: `php /var/www/isp_billing/cron/db_backup.php`
- ক্রন উদাহরণ (রাত ২:০১): `1 2 * * * /usr/bin/php /var/www/isp_billing/cron/db_backup.php >> /var/www/isp_billing/storage/logs/backup.log 2>&1`

#### 4. Client Management (`cron/auto_inactive.php`)
- Identifies inactive clients
- Sends suspension warnings
- Updates client statuses
- ম্যানুয়াল চালানো: `php /var/www/isp_billing/cron/auto_inactive.php`
- ক্রন উদাহরণ (প্রতি 30 মিনিট): `*/30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_inactive.php >> /var/www/isp_billing/storage/logs/auto_inactive.log 2>&1`

#### 5. Router PPPoE Sync (`cron/sync_clients.php`)
- কাজ: MikroTik PPP secret/active লিস্ট থেকে ক্লায়েন্ট (pppoe_id, পাসওয়ার্ড, প্রোফাইল→প্যাকেজ, অনলাইন স্ট্যাটাস) টেনে clients টেবিল আপডেট/ইনসার্ট করে।
- ম্যানুয়াল চালানো: `php /var/www/isp_billing/cron/sync_clients.php`
- ক্রন উদাহরণ (প্রতি ঘণ্টা): `5 * * * * /usr/bin/php /var/www/isp_billing/cron/sync_clients.php >> /var/www/isp_billing/storage/logs/sync_clients.log 2>&1`
- লগ পাথে নজর রাখুন: `storage/logs/sync_clients.log` (প্রয়োজনে `tools/log_watch.sh sync_clients.log` দিয়ে ফলো করুন)।

#### 5. SMS Reminders (`cron/sms_due_reminder.php`, `cron/sms_sender.php`)
- ক্রন উদাহরণ:
  - ডিউ ক্লায়েন্ট কিউ: `5 0 * * * /usr/bin/php /var/www/isp_billing/cron/sms_due_reminder.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1`
  - কিউ প্রসেসর (প্রতি 2 মিনিট): `*/2 * * * * /usr/bin/php /var/www/isp_billing/cron/sms_sender.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1`

### Server Cron Setup (প্র্যাক্টিক্যাল ধাপ)
- দ্রুত সেটআপ: `cat <<'EOF' | crontab -` দিয়ে নিচের ব্লক বসান (বিদ্যমান জব রাখা হয়েছে):
  ```
  SHELL=/bin/bash
  PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

  # Existing jobs
  #*/5 * * * * /usr/bin/php /var/www/isp_billing/api/olt_refresh_all.php community=public snmp_timeout=5000000 snmp_retries=3 >> /var/log/olt_refresh_all.log 2>&1
  */5 * * * * cd /var/www/isp_billing && /usr/bin/php api/olt_mac_refresh_telnet.php >> storage/logs/olt_mac_refresh_telnet.log 2>&1
  */5 * * * * echo "$(date) cron test" >> /var/log/cron_test.log

  # Billing & maintenance
  10 0 * * * /usr/bin/php /var/www/isp_billing/cron/auto_billing.php >> /var/www/isp_billing/storage/logs/auto_billing.log 2>&1
  */10 * * * * /usr/bin/php /var/www/isp_billing/cron/olt_poll.php >> /var/www/isp_billing/storage/logs/olt_poll.log 2>&1
  1 2 * * * /usr/bin/php /var/www/isp_billing/cron/db_backup.php >> /var/www/isp_billing/storage/logs/backup.log 2>&1
  */30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_inactive.php >> /var/www/isp_billing/storage/logs/auto_inactive.log 2>&1

  # Router sync
  5 * * * * /usr/bin/php /var/www/isp_billing/cron/sync_clients.php >> /var/www/isp_billing/storage/logs/sync_clients.log 2>&1

  # Notifications & suspensions
  5 0 * * * /usr/bin/php /var/www/isp_billing/cron/sms_due_reminder.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1
  */2 * * * * /usr/bin/php /var/www/isp_billing/cron/sms_sender.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1
  */10 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend_enable.php >> /var/www/isp_billing/storage/logs/cron_suspend.log 2>&1
  */30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend.php >> /var/www/isp_billing/storage/logs/auto_suspend.log 2>&1
  ```
- লগ পাথ: `storage/logs/*.log`; প্রথম রান মনিটর: `tail -f storage/logs/auto_billing.log` (অনুরূপ অন্যগুলিও)।
- `php` বাইনারি ভিন্ন হলে `/usr/bin/php` অংশ আপডেট করুন।

### Cron Reference Rows in DB
cron_runs টেবিলে রেফারেন্স রো রাখার প্রধান লাভগুলো:

দৃশ্যমানতা ও ইতিহাস: কোন জব কবে, কত সময় লাগিয়ে চলেছে—ড্যাশবোর্ডে এক জায়গায় দেখা যায়।
ট্রাবলশুটিং: ব্যর্থ হলে error/output দেখে দ্রুত কারণ ধরতে সুবিধা।
অডিট/দায়বদ্ধতা: triggered_by ও টাইমস্ট্যাম্প থাকায় কে চালিয়েছে/শিডিউল রান হয়েছে কিনা ট্র্যাক থাকে।
স্বাস্থ্য মনিটরিং: নিয়মিত রান হচ্ছে কিনা, স্কিপ/লেট হয়েছে কিনা সহজে বোঝা যায়; এলার্টিংয়ের ভিত্তি তৈরি করা যায়।
ডকুমেন্টেড শিডিউল: output-এ শিডিউল স্ট্রিং থাকায় ডাটাবেজেই রান-ফ্রিকোয়েন্সি/পাথ নথিভুক্ত থাকে; নতুন ডেভ/অ্যাডমিন দ্রুত বুঝতে পারে।
স্কেল/অটোমেশন: ভবিষ্যতে API/CLI থেকে জব ট্রিগার বা রিপোর্ট জেনারেট করা সহজ হয়, একক সোর্স অফ ট্রুথ হিসেবে কাজ করে।

- উদ্দেশ্য: `public/cron_dashboard.php` ও রিপোর্টিংয়ের জন্য প্রতিটি শিডিউলের রেফারেন্স রেকর্ড রাখা।
- চালানোর স্থান: `mysql -u <user> -p<pass> -D isp_billing` শেলে।
- ইনসার্ট স্ক্রিপ্ট (এই কাজেই ব্যবহার করেছি):
  ```sql
  INSERT INTO cron_runs (job_key,title,status,started_at,finished_at,duration_ms,output,error,triggered_by) VALUES
  ('auto_billing','Scheduled: auto_billing (daily 00:10)','success',NOW(),NOW(),0,'Crontab: 10 0 * * * /usr/bin/php /var/www/isp_billing/cron/auto_billing.php >> /var/www/isp_billing/storage/logs/auto_billing.log 2>&1','',NULL),
  ('olt_poll','Scheduled: olt_poll (every 10 mins)','success',NOW(),NOW(),0,'Crontab: */10 * * * * /usr/bin/php /var/www/isp_billing/cron/olt_poll.php >> /var/www/isp_billing/storage/logs/olt_poll.log 2>&1','',NULL),
  ('db_backup','Scheduled: db_backup (daily 02:01)','success',NOW(),NOW(),0,'Crontab: 1 2 * * * /usr/bin/php /var/www/isp_billing/cron/db_backup.php >> /var/www/isp_billing/storage/logs/backup.log 2>&1','',NULL),
  ('auto_inactive','Scheduled: auto_inactive (every 30 mins)','success',NOW(),NOW(),0,'Crontab: */30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_inactive.php >> /var/www/isp_billing/storage/logs/auto_inactive.log 2>&1','',NULL),
  ('sync_clients','Scheduled: sync_clients (hourly at :05)','success',NOW(),NOW(),0,'Crontab: 5 * * * * /usr/bin/php /var/www/isp_billing/cron/sync_clients.php >> /var/www/isp_billing/storage/logs/sync_clients.log 2>&1','',NULL),
  ('sms_due_reminder','Scheduled: sms_due_reminder (daily 00:05)','success',NOW(),NOW(),0,'Crontab: 5 0 * * * /usr/bin/php /var/www/isp_billing/cron/sms_due_reminder.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1','',NULL),
  ('sms_sender','Scheduled: sms_sender (every 2 mins)','success',NOW(),NOW(),0,'Crontab: */2 * * * * /usr/bin/php /var/www/isp_billing/cron/sms_sender.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1','',NULL),
  ('auto_suspend_enable','Scheduled: auto_suspend_enable (every 10 mins)','success',NOW(),NOW(),0,'Crontab: */10 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend_enable.php >> /var/www/isp_billing/storage/logs/cron_suspend.log 2>&1','',NULL),
  ('auto_suspend','Scheduled: auto_suspend (every 30 mins)','success',NOW(),NOW(),0,'Crontab: */30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend.php >> /var/www/isp_billing/storage/logs/auto_suspend.log 2>&1','',NULL);
  ```
- যাচাই কুয়েরি: `SELECT job_key,status,started_at FROM cron_runs ORDER BY id DESC;` এবং `SELECT COUNT(*) FROM cron_runs;`
- ড্যাশবোর্ড: ব্রাউজার → `/public/cron_dashboard.php` (লগইন প্রয়োজন হলে আগে লগইন) — এখান থেকে হিস্টরি ও ম্যানুয়াল রান সম্ভব।
- নতুন ক্রন যোগ করলে একই ফরম্যাটে `cron_runs`-এ রো যোগ করুন; `output` ফিল্ডে শিডিউল লিখে রাখুন।
- অতিরিক্ত (crontab-এর non-`cron/` জব) ইনসার্ট উদাহরণ:
  ```sql
  INSERT INTO cron_runs (job_key,title,status,started_at,finished_at,duration_ms,output,error,triggered_by) VALUES
  ('olt_mac_refresh_telnet','Existing cron: olt_mac_refresh_telnet (every 5 mins)','success',NOW(),NOW(),0,'Crontab: */5 * * * * cd /var/www/isp_billing && /usr/bin/php api/olt_mac_refresh_telnet.php >> storage/logs/olt_mac_refresh_telnet.log 2>&1','',NULL),
  ('cron_test','Existing cron: cron_test echo (every 5 mins)','success',NOW(),NOW(),0,'Crontab: */5 * * * * echo \"$(date) cron test\" >> /var/log/cron_test.log','',NULL);
  ```

### Client Traffic Logging (client_traffic_log)
- ক্রন স্ক্রিপ্ট: `php cron/save_client_traffic.php --base-url=http://localhost --token=<cron_token>` (token না দিলে `storage/cron_token.txt` বা env `CRON_TOKEN` থেকে নেবে)।
- cron_token সেট: `echo "your-secret-token" > storage/cron_token.txt` (এটি `api/client_live_status.php`-এ login bypass করে)।
- API আপডেট: `/api/client_live_status.php` এখন `cron_token` (query param বা `X-CRON-TOKEN` হেডার) সাপোর্ট করে; মিলে গেলে login দরকার হয় না।
- ডাটাবেসে রেফারেন্স (উদাহরণ): `INSERT INTO cron_runs (job_key,title,status,started_at,finished_at,duration_ms,output,error,triggered_by) VALUES ('save_client_traffic','Scheduled: save_client_traffic (manual run setup)','success',NOW(),NOW(),0,'Script: php cron/save_client_traffic.php --base-url=http://localhost --token=<cron_token>','',NULL);`
- পরামর্শিত শিডিউল (উদাহরণ): `*/10 * * * * php /var/www/isp_billing/cron/save_client_traffic.php --base-url=http://localhost --token=<cron_token> >> /var/www/isp_billing/storage/logs/save_client_traffic.log 2>&1`
- আউটপুট টেবিল: `client_traffic_log` (client_id, log_time, rx_speed, tx_speed, total_download_gb, total_upload_gb)

### Invoice/Billing Quick Commands
- মাসিক বিল জেনারেট (সব ক্লায়েন্ট): `http://localhost/public/invoice_generate.php?month=YYYY-MM&commit=1`
- নির্দিষ্ট রাউটার আইডি সহ: `http://localhost/public/invoice_generate.php?month=YYYY-MM&router_id=8&commit=1` (উদাহরণ)
- প্রোড সার্ভার উদাহরণ: `http://103.175.242.18/public/invoice_generate.php?month=2025-08&commit=1`

### Log Management
- **Application Logs**: `storage/logs/monitor.log`
- **Error Logs**: `storage/logs/error.log`
- **Audit Logs**: Database `audit_logs` table

### Performance Optimization
1. **Database Indexing**: Ensure proper indexes on frequently queried columns
2. **Caching**: Implement Redis/Memcached for session and data caching
3. **CDN**: Use CDN for static assets
4. **Database Optimization**: Regular ANALYZE and OPTIMIZE operations

### Automated Testing (PHPUnit)
- `tests/` ডিরেক্টরি থেকে ইউনিট টেস্ট চালাতে `bash tools/phpunit.sh` ব্যবহার করুন (Composer ছাড়াই phpunit-9.6 PHAR ডাউনলোড ও রান করে)।
- টেস্ট বুটস্ট্র্যাপ (`tests/bootstrap.php`) টাইমজোন ও হেল্পার ফাইল লোড করে, তাই আলাদা কিছু করার দরকার নেই।
- উদাহরণ টেস্ট: `tests/InvoiceHelperTest.php` ইনভয়েস টোটাল যাচাই করে, `tests/HelpersTest.php` MAC নরমালাইজ/লগ ফাইল লেখা পরীক্ষা করে।
- সবুজ ফল পেলে আউটপুটে `OK` দেখাবে; ব্যর্থ হলে সংশ্লিষ্ট ফাংশন রিভিউ করুন।
- কমান্ড নোট: সব টেস্ট `bash tools/phpunit.sh`; নির্দিষ্ট টেস্ট `bash tools/phpunit.sh --filter compute_invoice_total`; ভিন্ন PHP ভার্সনে চালাতে `PHP_BIN=/usr/bin/php8.2 bash tools/phpunit.sh`।

### Log Monitoring & Rotation
- রিয়েল-টাইম ফলো: `bash tools/log_watch.sh` (ডিফল্টে `monitor.log`, `error.log`, `alerts.log`, `auto_billing.log` টেইল-এ ফলো করে; নির্দিষ্ট ফাইল দিতে চাইলে আর্গুমেন্ট দিন)।
- লগ ঘুরানো (logrotate উদাহরণ): `tools/logrotate_isp_billing.conf` ফাইল `/etc/logrotate.d/`-এ কপি করলে দৈনিক রোটেশন, ১৪ কপি, কমপ্রেস, পারমিশন সেট আপ হবে।
- ইনস্টল স্ক্রিপ্ট: root/sudo থেকে `bash tools/install_logrotate.sh` চালালে কনফিগ `/etc/logrotate.d/logrotate_isp_billing.conf`-এ কপি হবে।
- ক্রন সেফ: logrotate সিস্টেমের ডিফল্ট দৈনিক ক্রনেই চলবে; হাতে চালাতে `logrotate -fv /etc/logrotate.d/logrotate_isp_billing.conf`।
- লগ পাথ: `storage/logs/` (অ্যাপ), `storage/logs/alerts.log` (SMS/ইমেইল প্লেসহোল্ডার), `storage/logs/auto_billing.log` (ক্রন) ইত্যাদি।
- দ্রুত নির্দেশ/কমান্ড:
  - ডিরেক্টরি: `cd /var/www/isp_billing`
  - logrotate ইনস্টল: `sudo bash tools/install_logrotate.sh`
  - ম্যানুয়াল রোটেশন টেস্ট: `sudo logrotate -fv /etc/logrotate.d/logrotate_isp_billing.conf`
  - সফল রোটেশনে আউটপুটে দেখাবে `rotating log ...` এবং নতুন ফাইল যেমন `auto_billing.log-YYYYMMDD` তৈরি হবে, তারপর নতুন খালি লগ `0640` মোডে।
  - রোটেশনের পর যাচাই: `ls -l storage/logs | head` এবং `tail -n 20 storage/logs/auto_billing.log` দেখে নতুন লগ লেখা হচ্ছে কি না নিশ্চিত করুন।

# crontab -e

SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# Existing jobs
#*/5 * * * * /usr/bin/php /var/www/isp_billing/api/olt_refresh_all.php community=public snmp_timeout=5000000 snmp_retries=3 >> /var/log/olt_refresh_all.log 2>&1
*/5 * * * * cd /var/www/isp_billing && /usr/bin/php api/olt_mac_refresh_telnet.php >> storage/logs/olt_mac_refresh_telnet.log 2>&1
*/5 * * * * echo "$(date) cron test" >> /var/log/cron_test.log

# Billing & maintenance
10 0 * * * /usr/bin/php /var/www/isp_billing/cron/auto_billing.php >> /var/www/isp_billing/storage/logs/auto_billing.log 2>&1
*/10 * * * * /usr/bin/php /var/www/isp_billing/cron/olt_poll.php >> /var/www/isp_billing/storage/logs/olt_poll.log 2>&1
1 2 * * * /usr/bin/php /var/www/isp_billing/cron/db_backup.php >> /var/www/isp_billing/storage/logs/backup.log 2>&1
*/30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_inactive.php >> /var/www/isp_billing/storage/logs/auto_inactive.log 2>&1

# Router sync
5 * * * * /usr/bin/php /var/www/isp_billing/cron/sync_clients.php >> /var/www/isp_billing/storage/logs/sync_clients.log 2>&1

# Notifications & suspensions
5 0 * * * /usr/bin/php /var/www/isp_billing/cron/sms_due_reminder.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1
*/2 * * * * /usr/bin/php /var/www/isp_billing/cron/sms_sender.php >> /var/www/isp_billing/storage/logs/sms.log 2>&1
*/10 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend_enable.php >> /var/www/isp_billing/storage/logs/cron_suspend.log 2>&1
*/30 * * * * /usr/bin/php /var/www/isp_billing/cron/auto_suspend.php >> /var/www/isp_billing/storage/logs/auto_suspend.log 2>&1




## Troubleshooting

### Common Issues

#### 1. Database Connection Errors
```php
// Check app/config.php settings
// Verify MySQL service is running
// Test connection with:
$pdo = new PDO($dsn, $username, $password);
```

#### 2. Router API Connection Issues
- Verify router IP and credentials
- Check RouterOS API port (default: 8728)
- Ensure router allows API connections
- Test with RouterOS Winbox

#### 3. OLT Communication Problems
- Verify OLT IP and credentials
- Check telnet/SSH connectivity
- Review OLT vendor compatibility
- Test with manual telnet connection

#### 4. Session Issues
- Check PHP session configuration
- Verify session storage permissions
- Clear browser cookies and cache
- Check SESSION_LIFETIME setting

#### 5. File Permission Errors
```bash
# Set proper permissions
chmod 755 storage/
chmod 755 uploads/
chmod 644 app/config.php
```

### Debug Mode
Enable debug mode in `app/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Log Analysis
Check logs for error patterns:
```bash
tail -f storage/logs/monitor.log
tail -f storage/logs/error.log
```

### Network Connectivity Checks (Router/OLT)
- MikroTik API পোর্ট 8728 টেস্ট: `nc -vz ROUTER_IP 8728` অথবা `telnet ROUTER_IP 8728`
- SSH টেস্ট (যদি কনফিগে SSH লাগে): `ssh user@ROUTER_IP`
- OLT টেলনেট/SSH টেস্ট: `telnet OLT_IP 23` বা `ssh oltuser@OLT_IP`
- SNMP রিচেবিলিটি (কমিউনিটি public উদাহরণ): `snmpwalk -v2c -c public ROUTER_IP system`
- ফায়ারওয়াল/NAT ব্লক আছে কি না দেখতে একই কমান্ড সার্ভার থেকেই চালান; পোর্ট ওপেন না থাকলে API/OLT স্ক্রিপ্ট কাজ করবে না।

## Quick Commands (Cheat Sheet)
- প্রজেক্ট ডিরেক্টরি: `cd /var/www/isp_billing`
- ডিপেনডেন্সি (যদি Composer ইনস্টল থাকে): `composer install --no-dev`
- PHPUnit টেস্ট: `bash tools/phpunit.sh`
- Log tail (রিয়েল-টাইম): `bash tools/log_watch.sh`
- Logrotate ইনস্টল: `sudo bash tools/install_logrotate.sh`
- ম্যানুয়াল ক্রন রান: `php cron/auto_billing.php` | `php cron/olt_poll.php` | `php cron/db_backup.php` | `php cron/auto_inactive.php` | `php cron/sync_clients.php`
- বিল জেনারেট URL: `http://localhost/public/invoice_generate.php?month=YYYY-MM&commit=1`
- ব্যাকআপ ডাম্প: `mysqldump -u root -p isp_billing > backups/isp_billing_$(date +%F).sql`

## Security Considerations

### 1. Password Security
- Upgrade from MD5 to bcrypt for password hashing
- Implement password complexity requirements
- Add password reset functionality

### 2. Database Security
- Use prepared statements (already implemented)
- Implement database user with minimal privileges
- Enable SSL for database connections

### 3. Session Security
- Use secure session cookies
- Implement session regeneration
- Add CSRF protection

### 4. File Upload Security
- Validate file types and sizes
- Store uploads outside web root
- Scan uploaded files for malware

## Future Enhancements

### 1. Security Improvements
- Implement OAuth2 authentication
- Add two-factor authentication
- Upgrade password hashing to bcrypt
- Add CSRF protection

### 2. Performance Optimizations
- Implement Redis caching
- Add database query optimization
- Use CDN for static assets
- Implement lazy loading

### 3. Feature Additions
- Mobile app development
- Advanced reporting dashboard
- Automated network diagnostics
- Integration with payment gateways
- Multi-language support

### 4. Monitoring & Alerting
- Real-time system monitoring
- Automated alert system
- Performance metrics dashboard
- Health check endpoints

### Deployment Checklist (প্রোড প্রস্তুতি)
- সিক্রেট/কনফিগ: DB ইউজার/পাস, SMTP, SMS গেটওয়ে, Telegram bot token, API কী আপডেট; পাবলিক রিপোতে রাখবেন না।
- পারমিশন: `chown -R www-data:www-data storage uploads backups` এবং `chmod -R 755 storage uploads backups`, `chmod 640 app/config.php`.
- Apache: সাইট এনাবল + `a2enmod rewrite`; কনফিগ টেস্ট `sudo apache2ctl configtest`; রিলোড `sudo systemctl reload apache2`.
- ক্রন: auto_billing, olt_poll, db_backup, auto_inactive, sms_due_reminder, sms_sender, sync_clients ইনস্টল করুন; লগ পাথ `storage/logs/*.log` এ যাচাই।
- SSL/TLS: 443 VirtualHost + LetsEncrypt; সেশন কুকিতে secure ফ্ল্যাগ নিশ্চিত করুন।
- ডাটাবেস: ন্যূনতম প্রিভিলেজ ইউজার, ব্যাকআপ রুটিন টেস্ট (`mysqldump`), টাইমজোন `Asia/Dhaka`.
- সিকিউরিটি: bcrypt/CSRF বাস্তবায়ন পরিকল্পনা; ফায়ারওয়াল রুলে API/DB/SSH পোর্ট লকডাউন; fail2ban/sshguard বিবেচনা।
- মনিটরিং: `tools/log_watch.sh` দিয়ে লগ ফলো; logrotate ইনস্টল; Zabbix/Prometheus এ CPU/RAM/ডিস্ক/DB কানেকশন/HTTP আপটাইম যোগ করুন।

---

## Support & Documentation

For additional support or documentation updates, please refer to:
- System logs in `storage/logs/`
- Database schema in `isp_billing.sql`
- API documentation in individual endpoint files
- User manual in `User Manual.txt`

This guide provides a comprehensive overview of the ISP Billing & Management System. For specific implementation details, refer to the source code and inline documentation.
