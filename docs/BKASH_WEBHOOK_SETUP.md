# bKash Webhook/Callback Configuration Guide

## Overview
This document describes how to configure bKash merchant webhooks and callbacks for real-time transaction updates in the ISP Billing System.

---

## 🔗 Webhook Endpoints

### 1. **PGW (Payment Gateway) Callback Endpoint**
**Purpose:** Receives tokenized checkout completion callbacks from bKash Portal  
**URL:** `https://YOUR_DOMAIN/api/bkash_pgw_callback.php`  
**Method:** `GET` (redirect from bKash Portal)  
**Response:** HTML (Bengali UI)  
**Behavior:**
- Accepts `paymentID` and `status` parameters from bKash redirect
- Calls bKash `executePayment()` API to confirm transaction
- Stores payment into `sms_inbox` table for auto-matching with invoices
- Updates system in real-time via cron jobs (`cron/auto_bkash_apply.php`)

---

### 2. **RTN (Real-Time Notification) Webhook Endpoint**
**Purpose:** Receives real-time payment notifications from bKash  
**URL:** `https://YOUR_DOMAIN/api/bkash_rtn/notify.php`  
**Method:** `POST`  
**Content-Type:** `application/json`  
**Authentication:** Token in header or query parameter  
**Response:** JSON (Bengali messages, `application/json; charset=utf-8`)

#### Headers
```
POST /api/bkash_rtn/notify.php HTTP/1.1
Host: YOUR_DOMAIN
Content-Type: application/json
X-Bkash-Webhook-Token: YOUR_TOKEN
```

#### Alternative (Query Parameter)
```
POST /api/bkash_rtn/notify.php?token=YOUR_TOKEN HTTP/1.1
```

#### Request Body Example
```json
{
  "eventType": "payment_success",
  "eventId": "evt_123456",
  "paymentID": "pay_xyz",
  "trxID": "TRX20251214123456",
  "transactionStatus": "COMPLETED",
  "amount": 1000.00,
  "customerMsisdn": "01712345678",
  "merchantInvoiceNumber": "INV-2025-001"
}
```

#### Response Examples

**Success (200)**
```json
{
  "ok": true,
  "message": "ওয়েবহুক গ্রহণ করা হয়েছে (RTN events টেবিলে সেভ হয়েছে)।",
  "id": 42
}
```

**Duplicate (200)**
```json
{
  "ok": true,
  "duplicate": true,
  "message": "ইভেন্ট আগেই সেভ করা হয়েছে।"
}
```

**Auth Failure (401)**
```json
{
  "ok": false,
  "message": "টোকেন মিলছে না।"
}
```

**Invalid Data (422)**
```json
{
  "ok": false,
  "message": "বৈধ JSON ডেটা পাওয়া যায়নি।"
}
```

---

## 📊 Transaction Flow

### PGW Checkout Flow
```
1. Portal User → Create Payment (bkash_pgw_create.php)
   ↓
2. System → bKash API (createPayment)
   ↓
3. User → bKash Portal (pay)
   ↓
4. bKash → Redirect to Callback
   ↓ (GET /api/bkash_pgw_callback.php?paymentID=...&status=success)
   ↓
5. Callback Handler:
   a. Call bKash executePayment API
   b. Store result into sms_inbox table
   c. Return HTML confirmation (Bengali)
   ↓
6. Cron Job (auto_bkash_apply.php):
   a. Read sms_inbox entries (gateway='bkash_webhook')
   b. Match with invoices
   c. Auto-apply payments
   d. Mark invoices as paid
   ↓
7. System Updated (real-time, within seconds)
```

### RTN Webhook Flow
```
1. bKash Payment Occurs
   ↓
2. bKash → POST /api/bkash_rtn/notify.php (real-time)
   ↓
3. Webhook Handler:
   a. Validate token (X-Bkash-Webhook-Token)
   b. Check optional IP whitelist
   c. Parse JSON payload
   d. Extract transaction fields
   e. Store into bkash_rtn_events table
   f. Return 200 JSON (Bengali message)
   ↓
4. Cron Job (bkash_rtn_process.php):
   a. Process pending RTN events
   b. Fetch invoice/client details
   c. Apply payment if matched
   ↓
5. System Updated (within 1-5 minutes, configurable)
```

---

## ⚙️ Configuration

### 1. **PGW Settings**
Go to: **Billing → bKash → PGW Settings** (`/public/settings_bkash.php`)

Configure:
- **Base URL:** bKash API endpoint (e.g., `https://checkout.sandbox.bkash.com` for testing)
- **Username, Password:** Merchant credentials
- **App Key, App Secret:** OAuth credentials
- **Callback URL:** Auto-generated as `https://YOUR_DOMAIN/api/bkash_pgw_callback.php`

### 2. **RTN Webhook Token**
**Location:** `Settings → bKash` (if available) or `app/config.php`

**Key:** `BKASH_RTN_WEBHOOK_TOKEN`  
**Example:**
```php
define('BKASH_RTN_WEBHOOK_TOKEN', 'your-secret-token-12345');
```

Or set via settings UI (if implemented).

### 3. **Optional: IP Whitelist**
**Key:** `BKASH_RTN_IP_WHITELIST`  
**Format:** Comma-separated IPs (e.g., `103.105.1.1, 103.105.1.2`)

---

## 🔐 Security

1. **Token Validation:** Always send token in:
   - Header: `X-Bkash-Webhook-Token`
   - Query: `?token=...`
   
2. **IP Whitelist (Optional):** Restrict POST to bKash IPs only
   ```
   BKASH_RTN_IP_WHITELIST=103.105.1.1,103.105.1.2
   ```

3. **HTTPS Only:** Always use HTTPS for production

4. **Duplicate Detection:** System checks `event_hash` (SHA256 of raw body) to prevent double-processing

---

## 📋 Database Tables

### `sms_inbox` (PGW Payments)
Stores callback data from `bkash_pgw_callback.php`:
- `gateway`: `'bkash_webhook'`
- `trx_id`: Transaction ID from bKash
- `amount`: Payment amount
- `msisdn_from`: Payer MSISDN
- `ref_code`: Invoice number
- `raw_body`: Full JSON response
- `meta_json`: Metadata (source, paymentID, etc.)

### `bkash_rtn_events` (RTN Webhooks)
Stores webhook data from `/api/bkash_rtn/notify.php`:
- `event_hash`: SHA256 hash (uniqueness key)
- `event_type`: Event type (e.g., `payment_success`)
- `trx_id`: Transaction ID
- `amount`: Payment amount
- `payer_msisdn`: Payer phone
- `merchant_invoice_number`: Invoice reference
- `raw_body`: Full JSON payload
- `headers_json`: Request headers
- `processed`: Boolean (0=pending, 1=processed)
- `applied_client_id`: Client ID if matched
- `applied_amount`: Amount applied

---

## 🛠️ Cron Jobs

### `cron/auto_bkash_apply.php`
**Purpose:** Auto-match and apply payments from `sms_inbox`  
**Schedule:** Every 5-10 minutes  
**Behavior:**
- Reads `sms_inbox` entries (gateway='bkash_webhook')
- Matches with invoices by `ref_code` (invoice number/client code)
- Updates `invoices.paid_amount`, `invoices.status`
- Logs results to `storage/logs/bkash_auto_apply.log`

### `cron/bkash_rtn_process.php`
**Purpose:** Process pending RTN events  
**Schedule:** Every 1-5 minutes  
**Behavior:**
- Reads `bkash_rtn_events` (processed=0)
- Attempts to match with invoices
- Calls `auto_bkash_apply` logic
- Updates `processed`, `processed_at`, `applied_client_id`

---

## 📲 bKash Dashboard Configuration

### Setting RTN Webhook URL in bKash Portal

1. Log into **bKash Merchant Dashboard**
2. Go to **Settings → Webhooks** or **API Configuration**
3. Add webhook URL:
   ```
   https://YOUR_DOMAIN/api/bkash_rtn/notify.php
   ```
4. Set token/secret (send in header):
   ```
   X-Bkash-Webhook-Token: YOUR_TOKEN
   ```
5. Select events:
   - ✓ Payment Success
   - ✓ Payment Failed
   - ✓ Refund Completed
   - (Others as needed)
6. **Test Webhook** → Verify `bkash_rtn_events` table gets entry
7. **Save**

---

## 🧪 Testing

### Test PGW Callback
```bash
curl -i "https://YOUR_DOMAIN/api/bkash_pgw_callback.php?paymentID=pay_test_123&status=success"
```

### Test RTN Webhook
```bash
curl -X POST https://YOUR_DOMAIN/api/bkash_rtn/notify.php \
  -H "Content-Type: application/json" \
  -H "X-Bkash-Webhook-Token: YOUR_TOKEN" \
  -d '{
    "eventType": "payment_success",
    "trxID": "TRX_TEST_001",
    "amount": 500,
    "customerMsisdn": "01712345678",
    "merchantInvoiceNumber": "INV-001"
  }'
```

### Check Logs
```bash
tail -f /var/www/isp_billing/storage/logs/bkash_auto_apply.log
tail -f /var/www/isp_billing/logs/bkash_rtn_cron.log
```

---

## 🎯 Real-Time System Updates

All responses are **in Bengali** and transactions update the system immediately:

1. **PGW Callback** → Stored in `sms_inbox` → Applied within 5-10 min (cron)
2. **RTN Webhook** → Stored in `bkash_rtn_events` → Processed within 1-5 min (cron)
3. **Dashboard** → View status at `/public/bkash_rtn_dashboard.php`
4. **Invoices** → Auto-marked as paid when matched

---

## 📞 Support

For issues, check:
- `/var/www/isp_billing/storage/logs/bkash_auto_apply.log`
- `/var/www/isp_billing/logs/bkash_rtn_cron.log`
- bKash RTN Dashboard (Billing → bKash → RTN Dashboard)

