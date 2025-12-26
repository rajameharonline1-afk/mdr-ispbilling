# bKash Merchant Webhook/Callback System
## Real-Time Transaction Updates with Bengali Response

**Version:** 1.0  
**Updated:** December 14, 2025  
**Language:** Bengali responses (সব রেসপন্স বাংলায়)

---

## 📋 Quick Start

### 1. Configure RTN Webhook Token
```bash
# Edit /var/www/isp_billing/app/config.php
define('BKASH_RTN_WEBHOOK_TOKEN', 'your-secure-token-12345');
```

### 2. Install Cron Jobs
```bash
sudo bash /var/www/isp_billing/tools/install_bkash_webhook_cron.sh
```

### 3. Add Webhook to bKash Dashboard
- Go to bKash Merchant Portal → Settings → Webhooks
- URL: `https://YOUR_DOMAIN/api/bkash_rtn/notify.php`
- Token Header: `X-Bkash-Webhook-Token: your-secure-token-12345`
- Select events: Payment Success, Payment Failed, Refund Completed

### 4. Test Webhook
```bash
bash /var/www/isp_billing/tools/webhook_test_bkash_rtn.sh your-secure-token-12345 YOUR_DOMAIN https
```

### 5. Monitor Dashboard
Visit: **Billing → bKash → RTN Dashboard**

---

## 🔗 Webhook Endpoints

### Endpoint 1: PGW Callback (Payment Gateway)
```
GET /api/bkash_pgw_callback.php?paymentID=...&status=success
```
- **Triggered by:** bKash Portal (user redirect)
- **Response:** HTML (Bengali)
- **Storage:** `sms_inbox` table
- **Processing:** Cron job every 5 minutes

### Endpoint 2: RTN Webhook (Real-Time Notification)
```
POST /api/bkash_rtn/notify.php
Header: X-Bkash-Webhook-Token: YOUR_TOKEN
Content-Type: application/json
```
- **Triggered by:** bKash servers (real-time)
- **Response:** JSON (Bengali)
- **Storage:** `bkash_rtn_events` table
- **Processing:** Cron job every 2 minutes

---

## 🔄 Real-Time System Update Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    bKash Payment Occurs                          │
└──────────────────────────┬──────────────────────────────────────┘
                           │
           ┌───────────────┴───────────────┐
           │                               │
           ▼                               ▼
    ┌──────────────────┐          ┌──────────────────┐
    │   PGW Callback   │          │   RTN Webhook    │
    │     (GET)        │          │     (POST)       │
    └────────┬─────────┘          └────────┬─────────┘
             │                             │
             ▼                             ▼
    ┌──────────────────┐          ┌──────────────────┐
    │   Execute API    │          │ Validate Token   │
    │   Store in DB    │          │ Parse JSON       │
    │  (sms_inbox)     │          │ Store in DB      │
    └────────┬─────────┘          │(bkash_rtn_events)│
             │                     └────────┬─────────┘
             │                             │
             └─────────────┬───────────────┘
                           │
                ┌──────────▼──────────┐
                │   HTML Response     │
                │   (Bengali)         │
                │   JSON Response     │
                │   (Bengali)         │
                └─────────────────────┘
                           │
                           ▼
                ┌─────────────────────────────────────┐
                │     Cron Jobs Process Data          │
                │  ┌─────────────────────────────────┐│
                │  │ bkash_rtn_process.php (every 2min)││
                │  │ - Read pending events           ││
                │  │ - Process transactions          ││
                │  └─────────────────────────────────┘│
                │  ┌─────────────────────────────────┐│
                │  │ auto_bkash_apply.php (every 5min) ││
                │  │ - Match with invoices           ││
                │  │ - Apply payments                ││
                │  │ - Update invoice status         ││
                │  └─────────────────────────────────┘│
                └─────────────────────────────────────┘
                           │
                           ▼
                ┌─────────────────────────────────────┐
                │  System Updated - Real-Time         │
                │  ✓ Payments recorded                │
                │  ✓ Invoices updated                 │
                │  ✓ Client account balanced          │
                │  ✓ Logs written (Bengali)           │
                └─────────────────────────────────────┘
```

---

## 📊 Transaction Processing Details

### Phase 1: Receipt (Immediate)
- Webhook endpoint receives transaction data
- Validates authentication token
- Checks for duplicates (by SHA256 hash)
- Stores to database (sms_inbox or bkash_rtn_events)
- Returns 200 response with Bengali message

**Time:** < 1 second

### Phase 2: Processing (Cron)
- RTN processor reads pending events (every 2 minutes)
- Extracts transaction fields from payload
- Queries invoice/payment tables
- Attempts to match with existing records

**Time:** 2-5 minutes

### Phase 3: Application (Cron)
- Auto-apply reads matched records
- Updates invoice `paid_amount`
- Sets invoice `status` to 'paid'
- Updates client account balance
- Logs results to file

**Time:** 5-10 minutes total

### Phase 4: Verification (Dashboard)
- Administrator views status at RTN Dashboard
- Can manually reprocess failed transactions
- Can view raw payload for debugging

**Time:** On-demand

---

## 🛡️ Security Features

### 1. Token Authentication
- Shared secret token prevents unauthorized access
- Token sent in header: `X-Bkash-Webhook-Token`
- Alternative: Query parameter `?token=...`
- Implemented: `hash_equals()` for constant-time comparison

### 2. IP Whitelisting (Optional)
```php
define('BKASH_RTN_IP_WHITELIST', '103.105.1.1,103.105.1.2');
```

### 3. Duplicate Prevention
- Payload hashed with SHA256
- Hash checked before insertion
- Constraint: `UNIQUE(event_hash)`

### 4. HTTPS Only
- All endpoints require HTTPS in production
- Self-signed cert OK for testing
- Real certificate recommended for public

### 5. Input Validation
- JSON schema validation
- Field type checking
- Amount >= 0
- MSISDN format validation

---

## 📋 Response Codes & Messages

### Success (200)
```json
{
  "ok": true,
  "message": "ওয়েবহুক গ্রহণ করা হয়েছে (RTN events টেবিলে সেভ হয়েছে)।",
  "id": 42
}
```

### Duplicate (200)
```json
{
  "ok": true,
  "duplicate": true,
  "message": "ইভেন্ট আগেই সেভ করা হয়েছে।"
}
```

### Bad Request (405)
```json
{
  "ok": false,
  "message": "শুধু POST গ্রহণ করা হয়।"
}
```

### Unauthorized (401)
```json
{
  "ok": false,
  "message": "টোকেন মিলছে না।"
}
```

### Invalid Data (422)
```json
{
  "ok": false,
  "message": "বৈধ JSON ডেটা পাওয়া যায়নি।"
}
```

### Server Error (500)
```json
{
  "ok": false,
  "message": "সার্ভার কনফিগার করা হয়নি (BKASH_RTN_WEBHOOK_TOKEN লাগবে)।"
}
```

**All messages in Bengali (বাংলা)**

---

## 📁 Key Files

### Webhook Handlers
- `/api/bkash_pgw_callback.php` - PGW callback receiver
- `/api/bkash_rtn/notify.php` - RTN webhook receiver

### Cron Jobs
- `/cron/bkash_rtn_process.php` - Process RTN events (every 2 min)
- `/cron/auto_bkash_apply.php` - Apply to invoices (every 5 min)

### Helpers
- `/app/bkash_rtn.php` - RTN storage functions
- `/app/bkash.php` - PGW token/verify functions
- `/app/bkash_tokenized.php` - Tokenized checkout

### Dashboard
- `/public/bkash_rtn_dashboard.php` - Monitor all webhooks
- `/api/bkash_rtn/get_event_detail.php` - Event detail API

### Configuration
- `/app/config.php` - Define `BKASH_RTN_WEBHOOK_TOKEN`
- `/public/settings_bkash.php` - PGW credentials UI

### Documentation
- `/docs/BKASH_WEBHOOK_SETUP.md` - Setup guide
- `/docs/BKASH_WEBHOOK_QUICK_REFERENCE.html` - Quick reference
- `/tools/webhook_test_bkash_rtn.sh` - Test script
- `/tools/install_bkash_webhook_cron.sh` - Cron installer

---

## 🔧 Configuration Checklist

- [ ] Define `BKASH_RTN_WEBHOOK_TOKEN` in `app/config.php`
- [ ] Run cron installer: `sudo bash tools/install_bkash_webhook_cron.sh`
- [ ] Configure PGW settings (Base URL, credentials)
- [ ] Add webhook URL to bKash Merchant Dashboard
- [ ] Test webhook endpoint: `bash tools/webhook_test_bkash_rtn.sh`
- [ ] Monitor logs: `tail -f storage/logs/bkash_auto_apply.log`
- [ ] View RTN Dashboard: `/public/bkash_rtn_dashboard.php`
- [ ] Enable cron service: `sudo systemctl enable cron`

---

## 📊 Database Schema

### Table: `bkash_rtn_events`
```sql
CREATE TABLE bkash_rtn_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_hash CHAR(64) UNIQUE NOT NULL,      -- SHA256 for dedup
  event_type VARCHAR(100),                  -- payment_success, etc.
  event_id VARCHAR(100),
  payment_id VARCHAR(100),
  trx_id VARCHAR(100),
  status VARCHAR(50),                       -- COMPLETED, PENDING, etc.
  amount DECIMAL(12,2),
  payer_msisdn VARCHAR(20),
  merchant_invoice_number VARCHAR(100),
  raw_body LONGTEXT,                        -- Full JSON payload
  headers_json JSON,                        -- Request headers
  received_at TIMESTAMP,
  remote_ip VARCHAR(45),
  processed TINYINT(1) DEFAULT 0,           -- 0=pending, 1=done
  process_attempts INT DEFAULT 0,
  processed_at TIMESTAMP NULL,
  applied_client_id INT,                    -- Matched client
  applied_amount DECIMAL(12,2),
  last_error TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY(trx_id),
  KEY(status),
  KEY(processed),
  KEY(received_at)
);
```

### Table: `sms_inbox` (For PGW)
```
gateway='bkash_webhook'  -- Identifies PGW payments
trx_id                   -- bKash transaction ID
amount                   -- Payment amount
msisdn_from              -- Payer MSISDN
ref_code                 -- Invoice reference
raw_body                 -- Full response JSON
meta_json                -- Additional metadata
```

---

## 🧪 Testing Commands

### Test RTN Webhook
```bash
curl -X POST https://YOUR_DOMAIN/api/bkash_rtn/notify.php \
  -H "Content-Type: application/json" \
  -H "X-Bkash-Webhook-Token: YOUR_TOKEN" \
  -d '{
    "eventType": "payment_success",
    "trxID": "TRX_TEST_001",
    "amount": 1000,
    "customerMsisdn": "01712345678",
    "merchantInvoiceNumber": "INV-2025-001"
  }'
```

### View Recent Events
```bash
mysql -u root -p isp_billing -e \
  "SELECT id, trx_id, amount, status, processed, received_at 
   FROM bkash_rtn_events 
   ORDER BY id DESC LIMIT 10;"
```

### Check Cron Logs
```bash
tail -50 /var/www/isp_billing/storage/logs/bkash_auto_apply.log
tail -50 /var/www/isp_billing/logs/bkash_rtn_cron.log
```

### View Dashboard
```
https://YOUR_DOMAIN/public/bkash_rtn_dashboard.php
```

---

## 📞 Support & Troubleshooting

### Webhook Not Received?
1. Check bKash Dashboard → Webhook history
2. Verify token matches: `app/config.php` vs bKash settings
3. Check firewall: Port 443 must be open
4. Verify HTTPS certificate is valid
5. Test manually: See "Testing Commands" above

### Payment Not Applied?
1. Check logs: `tail -f storage/logs/bkash_auto_apply.log`
2. Verify cron jobs installed: `crontab -l` (as www-data)
3. Check database for `bkash_rtn_events` entry
4. View RTN Dashboard → Check status

### Cron Jobs Not Running?
1. Verify cron service: `sudo systemctl status cron`
2. Check cron file: `cat /etc/cron.d/isp_billing_bkash`
3. Test manually: `php /var/www/isp_billing/cron/auto_bkash_apply.php`
4. Check permissions: `ls -la /var/www/isp_billing/cron/`

### Database Errors?
1. Check table exists: `SHOW TABLES LIKE 'bkash_rtn_events';`
2. If missing, run: `mysql -u root -p isp_billing < tools/bkash_rtn_events.sql`
3. Verify columns: `DESCRIBE bkash_rtn_events;`

---

## 📈 Performance Notes

- **Receipt:** < 1 second (immediate)
- **Processing:** 2-5 minutes (cron interval)
- **Application:** 5-10 minutes (total)
- **Dashboard load:** < 1 second (cached stats)

For faster processing, reduce cron interval:
```bash
# Edit /etc/cron.d/isp_billing_bkash
*/1 * * * * www-data php ...  # Every 1 minute
```

---

## 🎯 Summary

✅ **Two webhook endpoints** handle real-time payment updates  
✅ **Bengali responses** for all user-facing messages  
✅ **Automatic matching** between payments and invoices  
✅ **Secure token authentication** prevents unauthorized access  
✅ **Duplicate detection** using SHA256 hashing  
✅ **Cron-based processing** for guaranteed delivery  
✅ **Dashboard monitoring** for easy troubleshooting  
✅ **Full logging** for audit and debugging  

---

**Last Updated:** December 14, 2025  
**Next Review:** June 14, 2026
