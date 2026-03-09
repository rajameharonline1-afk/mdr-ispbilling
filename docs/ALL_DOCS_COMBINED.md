# Combined Docs Bundle

Generated on: 2026-03-09

## File: API_CONTRACT_PARITY_TEMPLATE.md

```
# API Contract Parity Template

Purpose:
- Legacy API vs Laravel API field-level compatibility track করা
- Breaking change production-এ যাওয়ার আগেই detect করা

How to use:
- প্রতিটি critical API endpoint এর জন্য নিচের section copy করুন
- Legacy response sample ও Laravel response sample দিন
- Key-by-key comparison table fill করুন

## Global Rules

- Path compatibility: first phase-এ legacy path support রাখতে হবে
- Response keys rename করা যাবে না (unless approved change request)
- Status code parity maintain করতে হবে
- Financial/payment endpoints-এ strict idempotency enforce করতে হবে

## Endpoint Contract Sheet (Copy per endpoint)

### 1) Endpoint Metadata

- Legacy Path:
- Laravel Path:
- Method:
- Module:
- Auth Requirement:
- Owner:
- Priority: `P0/P1/P2`

### 2) Request Contract

| Field | Type | Required | Validation Rule (Legacy) | Validation Rule (Laravel) | Match (Y/N) | Notes |
|---|---|---|---|---|---|---|
|  |  |  |  |  |  |  |

### 3) Success Response Contract

- Legacy status code:
- Laravel status code:

| Key Path | Type | Legacy Example | Laravel Example | Match (Y/N) | Notes |
|---|---|---|---|---|---|
|  |  |  |  |  |  |

### 4) Error Response Contract

| Scenario | Legacy Status | Laravel Status | Legacy Error Shape | Laravel Error Shape | Match (Y/N) | Notes |
|---|---:|---:|---|---|---|---|
| Validation failed |  |  |  |  |  |  |
| Unauthorized |  |  |  |  |  |  |
| Server error |  |  |  |  |  |  |

### 5) Behavior Parity

| Behavior Check | Legacy | Laravel | Match (Y/N) | Evidence |
|---|---|---|---|---|
| Same DB side-effect |  |  |  |  |
| Duplicate request handling |  |  |  |  |
| Timezone/date handling |  |  |  |  |
| Idempotency (if applicable) |  |  |  |  |

### 6) Test Evidence

- Unit test file:
- Integration test file:
- Contract test file:
- Last run date:
- CI build link/reference:

### 7) Final Verdict

- Contract parity status: `MATCHED / PARTIAL / MISMATCH`
- Blocking issues:
- Approved by:
- Approval date:

---

## P0 Endpoint Master Tracker

| Endpoint | Module | Owner | Request Match | Success Match | Error Match | Behavior Match | Overall | Go-Live Blocker |
|---|---|---|---|---|---|---|---|---|
| /api/invoice_create.php | Invoice | TBD | N | N | N | N | MISMATCH | YES |
| /api/payment_mark_paid.php | Payment | TBD | N | N | N | N | MISMATCH | YES |
| /api/bkash_webhook.php | Payment | TBD | N | N | N | N | MISMATCH | YES |

## Sign-off Checklist

- [ ] সব P0 endpoint-এর contract sheet complete
- [ ] সব P0 endpoint-এর overall status `MATCHED`
- [ ] Mismatch items-এর change request documented
- [ ] QA sign-off complete
- [ ] Tech lead sign-off complete

```

## File: BKASH_WEBHOOK_IMPLEMENTATION.md

```
# bKash Webhook Implementation Summary
**ISP Billing System - Real-Time Payment Updates**

---

## ✅ What Has Been Implemented

### 1. **Two Complete Webhook Endpoints**

#### Endpoint A: PGW Callback (`/api/bkash_pgw_callback.php`)
- Receives redirect from bKash Portal after user pays
- Executes payment via bKash API
- Stores transaction in `sms_inbox` table
- Returns HTML response (Bengali)
- **Response Time:** Immediate
- **Processing:** Every 5 minutes via cron

#### Endpoint B: RTN Webhook (`/api/bkash_rtn/notify.php`)
- Receives real-time payment notifications from bKash
- Validates security token (header or query param)
- Stores event in `bkash_rtn_events` table
- Returns JSON response (Bengali)
- **Response Time:** < 1 second
- **Processing:** Every 2 minutes via cron

### 2. **Real-Time System Updates**

Both endpoints update the system through automated cron jobs:

```
Payment Received → Webhook Stored → Cron Process → Invoice Updated → System Live
(immediate)      (< 1 sec)         (every 2-5 min)  (within 5-10 min) (✓)
```

### 3. **Bengali Response Messages**

**All responses are in Bengali (বাংলা):**

- Success: "ওয়েবহুক গ্রহণ করা হয়েছে"
- Duplicate: "ইভেন্ট আগেই সেভ করা হয়েছে"
- Error: "টোকেন মিলছে না"
- Invalid Data: "বৈধ JSON ডেটা পাওয়া যায়নি"

### 4. **Security Features**

✓ Token-based authentication (header/query)  
✓ Optional IP whitelisting  
✓ Duplicate detection (SHA256 hash)  
✓ HTTPS required  
✓ Input validation  
✓ Constant-time token comparison  

### 5. **Monitoring & Dashboard**

**Location:** Billing → bKash → RTN Dashboard  
**URL:** `/public/bkash_rtn_dashboard.php`

Features:
- Real-time event count and statistics
- Filterable event table
- Event detail modal with raw payload
- Payment matching status
- Error tracking

### 6. **Automated Processing**

Two cron jobs run continuously:

1. **bkash_rtn_process.php** (every 2 minutes)
   - Reads pending RTN events
   - Attempts to match with invoices
   - Updates event status

2. **auto_bkash_apply.php** (every 5 minutes)
   - Matches payments with invoices
   - Auto-applies payments
   - Updates invoice balance

---

## 🔗 Request/Response Examples

### RTN Webhook Request
```bash
POST /api/bkash_rtn/notify.php HTTP/1.1
Host: YOUR_DOMAIN
Content-Type: application/json
X-Bkash-Webhook-Token: YOUR_SECRET_TOKEN

{
  "eventType": "payment_success",
  "eventId": "evt_20251214_001",
  "trxID": "TRX20251214000001",
  "transactionStatus": "COMPLETED",
  "amount": 1000.00,
  "customerMsisdn": "01712345678",
  "merchantInvoiceNumber": "INV-2025-001"
}
```

### RTN Webhook Response (200 OK)
```json
{
  "ok": true,
  "message": "ওয়েবহুক গ্রহণ করা হয়েছে (RTN events টেবিলে সেভ হয়েছে)।",
  "id": 42
}
```

### RTN Webhook Response (401 Unauthorized)
```json
{
  "ok": false,
  "message": "টোকেন মিলছে না।"
}
```

---

## 📂 New & Modified Files

### Documentation
- ✅ `/docs/BKASH_WEBHOOK_SETUP.md` - Complete setup guide
- ✅ `/docs/BKASH_WEBHOOK_QUICK_REFERENCE.html` - Quick reference (HTML)
- ✅ `/docs/BKASH_WEBHOOK_REALTIME.md` - Deep dive on real-time updates

### Tools & Scripts
- ✅ `/tools/webhook_test_bkash_rtn.sh` - Test webhook endpoint
- ✅ `/tools/install_bkash_webhook_cron.sh` - Install cron jobs

### Sidebar Menu
- ✅ Updated `/partials/partials_header.php` - Added bKash submenu under Billing
  - RTN Dashboard
  - Webhook Inbox
  - Manual Payments
  - Payments Inbox
  - Match Payments
  - SMS Tester
  - PGW Settings

### Dashboard
- ✅ `/public/bkash_rtn_dashboard.php` - Display RTN events with filters & modals
- ✅ `/api/bkash_rtn/get_event_detail.php` - Event detail API

### Existing Files (Already Complete)
- `/api/bkash_pgw_callback.php` - PGW callback endpoint
- `/api/bkash_rtn/notify.php` - RTN webhook endpoint
- `/app/bkash_rtn.php` - RTN storage helpers
- `/app/bkash.php` - PGW helpers
- `/cron/bkash_rtn_process.php` - RTN cron job
- `/cron/auto_bkash_apply.php` - Auto-apply cron job

---

## 🚀 Quick Start

### Step 1: Set Token
```php
// In /app/config.php
define('BKASH_RTN_WEBHOOK_TOKEN', 'your-secret-token-here-12345');
```

### Step 2: Install Cron Jobs
```bash
sudo bash /var/www/isp_billing/tools/install_bkash_webhook_cron.sh
```

### Step 3: Add Webhook to bKash Dashboard
```
URL: https://YOUR_DOMAIN/api/bkash_rtn/notify.php
Header: X-Bkash-Webhook-Token: your-secret-token-here-12345
Events: Payment Success, Payment Failed, Refund Completed
```

### Step 4: Test
```bash
bash /var/www/isp_billing/tools/webhook_test_bkash_rtn.sh \
  your-secret-token-here-12345 \
  YOUR_DOMAIN \
  https
```

### Step 5: Monitor
- Visit: **Billing → bKash → RTN Dashboard**
- Check logs: `tail -f /var/www/isp_billing/storage/logs/bkash_auto_apply.log`

---

## 📊 System Flow

```
┌────────────────────┐
│  bKash Payment     │
│  Occurs            │
└─────────┬──────────┘
          │
    ┌─────┴──────┐
    │             │
    ▼             ▼
  PGW         RTN Webhook
  Callback    (POST)
  (GET)       
    │             │
    ▼             ▼
store in      store in
sms_inbox     bkash_rtn_events
    │             │
    └─────┬───────┘
          │
          ▼
    ┌──────────────────┐
    │  Cron Processing │
    │  (every 2-5 min) │
    └────────┬─────────┘
             │
    ┌────────┴──────────┐
    │                   │
    ▼                   ▼
Match with         Update
Invoice            Event Status
    │                   │
    └────────┬──────────┘
             │
             ▼
    ┌──────────────────┐
    │ Apply Payment    │
    │ & Update         │
    │ Invoice Balance  │
    └────────┬─────────┘
             │
             ▼
    ┌──────────────────┐
    │  System Updated  │
    │  (LIVE)          │
    │  ✓ Real-Time     │
    │  ✓ Bengali msgs  │
    │  ✓ Logged        │
    └──────────────────┘
```

---

## 🔐 Security Checklist

✅ Token authentication required  
✅ Optional IP whitelist support  
✅ Duplicate detection (SHA256)  
✅ HTTPS only (production)  
✅ Input validation  
✅ Constant-time comparison  
✅ Rate limiting (implicit via cron)  
✅ Logging for audit trail  

---

## 📈 Performance

| Operation | Time | Triggered |
|-----------|------|-----------|
| Webhook receipt | <1 sec | Immediate |
| Token validation | <1 sec | Immediate |
| Storage in DB | <1 sec | Immediate |
| Response sent | <1 sec | Immediate |
| Cron process | 2-5 min | Scheduled |
| Invoice update | 5-10 min | Total |
| Dashboard load | <1 sec | On demand |

---

## 🛠️ Troubleshooting

### No Webhook Received?
1. Check bKash Dashboard for webhook logs
2. Verify token matches in `app/config.php`
3. Test manually: `curl -X POST ...` (see testing docs)
4. Check firewall port 443

### Payment Not Applied?
1. Check logs: `tail -f storage/logs/bkash_auto_apply.log`
2. View RTN Dashboard for status
3. Verify cron jobs: `sudo crontab -u www-data -l`
4. Test cron manually: `php cron/auto_bkash_apply.php`

### Cron Not Running?
1. Verify: `sudo systemctl status cron`
2. Check file: `cat /etc/cron.d/isp_billing_bkash`
3. Restart: `sudo systemctl restart cron`

---

## 📞 Documentation Files

| Document | Purpose | Format |
|----------|---------|--------|
| BKASH_WEBHOOK_SETUP.md | Complete setup guide | Markdown |
| BKASH_WEBHOOK_REALTIME.md | Real-time deep dive | Markdown |
| BKASH_WEBHOOK_QUICK_REFERENCE.html | Quick reference | HTML |

**View them at:**
- `/docs/BKASH_WEBHOOK_SETUP.md`
- `/docs/BKASH_WEBHOOK_REALTIME.md`
- `/docs/BKASH_WEBHOOK_QUICK_REFERENCE.html`

---

## 🎯 Key Takeaways

1. **Two Endpoints**: PGW Callback (GET) + RTN Webhook (POST)
2. **Always Bengali**: All user-facing messages in Bengali
3. **Automatic Updates**: Cron jobs process and apply payments
4. **Secure**: Token auth + duplicate detection + validation
5. **Monitored**: Dashboard shows all events with filtering
6. **Fast**: Receipt < 1 sec, total update 5-10 minutes
7. **Reliable**: Logging, error handling, duplicate prevention

---

## ✨ Next Steps

1. **Set token** in `app/config.php`
2. **Install cron** with `install_bkash_webhook_cron.sh`
3. **Configure bKash** Dashboard with webhook URL
4. **Test webhook** with test script
5. **Monitor logs** regularly
6. **View dashboard** to verify operations

---

**Webhook Integration: Complete ✅**  
**All responses in Bengali ✅**  
**Real-time updates enabled ✅**


```

## File: BKASH_WEBHOOK_QUICK_REFERENCE.html

```
<!-- /docs/BKASH_WEBHOOK_QUICK_REFERENCE.html -->
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>bKash Webhook দ্রুত রেফারেন্স</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      line-height: 1.6;
      color: #333;
      background: #f5f5f5;
      padding: 20px;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h1 { color: #0066cc; margin-bottom: 10px; font-size: 28px; }
    h2 { color: #0066cc; margin-top: 25px; margin-bottom: 15px; font-size: 20px; border-bottom: 2px solid #0066cc; padding-bottom: 8px; }
    h3 { color: #333; margin-top: 15px; margin-bottom: 10px; font-size: 16px; }
    .endpoint {
      background: #f9f9f9;
      padding: 15px;
      margin: 15px 0;
      border-left: 4px solid #0066cc;
      border-radius: 4px;
    }
    .endpoint-url {
      font-family: 'Courier New', monospace;
      background: #e8f4f8;
      padding: 10px;
      border-radius: 4px;
      margin: 10px 0;
      overflow-x: auto;
      color: #d83b2b;
      font-weight: bold;
    }
    .method {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: bold;
      font-size: 12px;
      margin-right: 10px;
    }
    .method.get { background: #61affe; color: white; }
    .method.post { background: #49cc90; color: white; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin: 15px 0;
    }
    th, td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    th {
      background: #f0f0f0;
      font-weight: bold;
      color: #333;
    }
    tr:hover { background: #f9f9f9; }
    code {
      background: #f4f4f4;
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Courier New', monospace;
      font-size: 13px;
    }
    .highlight {
      background: #fff3cd;
      padding: 15px;
      border-radius: 4px;
      border-left: 4px solid #ffc107;
      margin: 15px 0;
    }
    .success {
      background: #d4edda;
      border-left-color: #28a745;
      color: #155724;
    }
    .danger {
      background: #f8d7da;
      border-left-color: #dc3545;
      color: #721c24;
    }
    .info {
      background: #d1ecf1;
      border-left-color: #17a2b8;
      color: #0c5460;
    }
    .command {
      background: #282c34;
      color: #abb2bf;
      padding: 15px;
      border-radius: 4px;
      margin: 10px 0;
      overflow-x: auto;
      font-family: 'Courier New', monospace;
      font-size: 13px;
      line-height: 1.5;
    }
    .response-example {
      background: #f4f4f4;
      padding: 15px;
      border-radius: 4px;
      font-family: 'Courier New', monospace;
      font-size: 12px;
      overflow-x: auto;
      margin: 10px 0;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin: 20px 0;
    }
    .card {
      background: #f9f9f9;
      padding: 15px;
      border-radius: 4px;
      border: 1px solid #ddd;
    }
    .footer {
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid #ddd;
      text-align: center;
      color: #666;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🪝 bKash Webhook দ্রুত রেফারেন্স</h1>
    <p style="color: #666; margin-bottom: 20px;">ISP Billing System এ রিয়েল-টাইম পেমেন্ট আপডেটের জন্য Webhook কনফিগারেশন গাইড</p>

    <!-- Overview -->
    <h2>📋 সারসংক্ষেপ</h2>
    <div class="grid">
      <div class="card">
        <h3>PGW Callback</h3>
        <p><strong>উদ্দেশ্য:</strong> bKash Portal থেকে পেমেন্ট সম্পন্নতার নোটিফিকেশন</p>
        <p><strong>পদ্ধতি:</strong> GET (Redirect)</p>
        <p><strong>রেসপন্স:</strong> HTML (বাংলা)</p>
        <p><strong>স্টোরেজ:</strong> sms_inbox টেবিল</p>
      </div>
      <div class="card">
        <h3>RTN Webhook</h3>
        <p><strong>উদ্দেশ্য:</strong> বাস্তব-সময় পেমেন্ট নোটিফিকেশন (Webhook)</p>
        <p><strong>পদ্ধতি:</strong> POST (JSON)</p>
        <p><strong>প্রমাণীকরণ:</strong> টোকেন হেডার/Query</p>
        <p><strong>স্টোরেজ:</strong> bkash_rtn_events টেবিল</p>
      </div>
    </div>

    <!-- PGW Callback -->
    <h2>1️⃣ PGW Callback Endpoint</h2>
    <div class="endpoint">
      <h3>PGW পেমেন্ট সম্পন্নতা (PGW Payment Completion)</h3>
      <div class="method get">GET</div>
      <div class="endpoint-url">https://YOUR_DOMAIN/api/bkash_pgw_callback.php?paymentID=...&status=success</div>
      
      <h4 style="margin-top: 15px;">প্যারামিটার:</h4>
      <table>
        <tr>
          <th>প্যারামিটার</th>
          <th>বিবরণ</th>
          <th>উদাহরণ</th>
        </tr>
        <tr>
          <td><code>paymentID</code></td>
          <td>bKash পেমেন্ট আইডি (প্রয়োজনীয়)</td>
          <td><code>pay_20251214123456</code></td>
        </tr>
        <tr>
          <td><code>status</code></td>
          <td>লেনদেন অবস্থা (success/failure)</td>
          <td><code>success</code></td>
        </tr>
      </table>

      <h4>প্রক্রিয়াকরণ:</h4>
      <ol style="margin-left: 20px; margin-top: 10px;">
        <li>✓ bKash executePayment API কল করে পেমেন্ট সম্পাদন করে</li>
        <li>✓ লেনদেনের বিস্তারিত <code>sms_inbox</code> টেবিলে সংরক্ষণ করে</li>
        <li>✓ বাংলা HTML রেসপন্স দেখায়</li>
        <li>⏳ Cron job (<code>auto_bkash_apply.php</code>) প্রতি ৫-১০ মিনিটে ইনভয়েসের সাথে ম্যাচ করে এবং প্রয়োগ করে</li>
      </ol>

      <h4>সফল উত্তর:</h4>
      <div class="response-example">
        &lt;h3&gt;পেমেন্ট সম্পন্ন হয়েছে&lt;/h3&gt;
        &lt;p&gt;ট্রান্স্যাকশন আইডি: TRX20251214000001&lt;/p&gt;
        &lt;p&gt;অ্যামাউন্ট: 1000.00&lt;/p&gt;
      </div>
    </div>

    <!-- RTN Webhook -->
    <h2>2️⃣ RTN Webhook Endpoint</h2>
    <div class="endpoint">
      <h3>রিয়েল-টাইম পেমেন্ট নোটিফিকেশন (Real-Time Notifications)</h3>
      <div class="method post">POST</div>
      <div class="endpoint-url">https://YOUR_DOMAIN/api/bkash_rtn/notify.php</div>

      <h4 style="margin-top: 15px;">হেডার:</h4>
      <div class="response-example">
Content-Type: application/json
X-Bkash-Webhook-Token: YOUR_SECRET_TOKEN
      </div>

      <h4>বিকল্প (Query Parameter):</h4>
      <div class="response-example">
https://YOUR_DOMAIN/api/bkash_rtn/notify.php?token=YOUR_SECRET_TOKEN
      </div>

      <h4>পেলোড উদাহরণ:</h4>
      <div class="response-example">
{
  "eventType": "payment_success",
  "eventId": "evt_123456",
  "trxID": "TRX20251214000001",
  "transactionStatus": "COMPLETED",
  "amount": 1000.00,
  "customerMsisdn": "01712345678",
  "merchantInvoiceNumber": "INV-2025-001"
}
      </div>

      <h4>প্রক্রিয়াকরণ:</h4>
      <ol style="margin-left: 20px; margin-top: 10px;">
        <li>✓ টোকেন যাচাই করে (Header বা Query)</li>
        <li>✓ JSON পেলোড বিশ্লেষণ করে</li>
        <li>✓ <code>bkash_rtn_events</code> টেবিলে ইভেন্ট সংরক্ষণ করে</li>
        <li>✓ JSON রেসপন্স দেয় (বাংলা মেসেজ সহ)</li>
        <li>⏳ Cron job (<code>bkash_rtn_process.php</code>) প্রতি ১-৫ মিনিটে পেন্ডিং ইভেন্ট প্রসেস করে</li>
      </ol>

      <h4>সফল উত্তর (200):</h4>
      <div class="response-example" style="color: #28a745;">
{
  "ok": true,
  "message": "ওয়েবহুক গ্রহণ করা হয়েছে (RTN events টেবিলে সেভ হয়েছে)।",
  "id": 42
}
      </div>

      <h4>ত্রুটি - অনুমোদন ব্যর্থ (401):</h4>
      <div class="response-example" style="color: #dc3545;">
{
  "ok": false,
  "message": "টোকেন মিলছে না।"
}
      </div>

      <h4>ত্রুটি - অবৈধ JSON (422):</h4>
      <div class="response-example" style="color: #dc3545;">
{
  "ok": false,
  "message": "বৈধ JSON ডেটা পাওয়া যায়নি।"
}
      </div>
    </div>

    <!-- Configuration -->
    <h2>⚙️ কনফিগারেশন</h2>

    <h3>১. RTN টোকেন সেট করুন</h3>
    <p>ফাইল: <code>/var/www/isp_billing/app/config.php</code></p>
    <div class="response-example">
define('BKASH_RTN_WEBHOOK_TOKEN', 'your-secret-token-here-12345');
    </div>
    <p style="margin-top: 10px;"><strong>অথবা</strong> Settings টেবিলে সংরক্ষণ করুন।</p>

    <h3>২. bKash ড্যাশবোর্ডে Webhook URL যোগ করুন</h3>
    <ol style="margin-left: 20px; margin-top: 10px;">
      <li>bKash Merchant Dashboard এ লগইন করুন</li>
      <li>Settings → Webhooks এ যান</li>
      <li>এই URL যোগ করুন: <code>https://YOUR_DOMAIN/api/bkash_rtn/notify.php</code></li>
      <li>টোকেন হেডারে পাঠান: <code>X-Bkash-Webhook-Token: YOUR_TOKEN</code></li>
      <li>ইভেন্ট সিলেক্ট করুন:
        <ul style="margin-top: 10px;">
          <li>✓ Payment Success</li>
          <li>✓ Payment Failed</li>
          <li>✓ Refund Completed</li>
        </ul>
      </li>
      <li>Test করুন → DB চেক করুন</li>
      <li>Save করুন</li>
    </ol>

    <!-- Testing -->
    <h2>🧪 টেস্টিং</h2>

    <h3>RTN Webhook টেস্ট করুন</h3>
    <div class="response-example">
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
    </div>

    <h3>স্বয়ংক্রিয় Test স্ক্রিপ্ট চালান</h3>
    <div class="response-example">
bash /var/www/isp_billing/tools/webhook_test_bkash_rtn.sh YOUR_TOKEN YOUR_DOMAIN https
    </div>

    <!-- Monitoring -->
    <h2>📊 মনিটরিং</h2>

    <div class="grid">
      <div class="card">
        <h3>Dashboard দেখুন</h3>
        <p><strong>URL:</strong> <code>/public/bkash_rtn_dashboard.php</code></p>
        <p>সমস্ত RTN ইভেন্ট, পেন্ডিং ট্রানজেকশন, এবং ত্রুটি দেখুন।</p>
      </div>
      <div class="card">
        <h3>Logs চেক করুন</h3>
        <p><code>tail -f /var/www/isp_billing/storage/logs/bkash_auto_apply.log</code></p>
        <p>Cron job প্রসেসিং লগ দেখুন।</p>
      </div>
      <div class="card">
        <h3>Database কোয়েরি</h3>
        <p><code>SELECT * FROM bkash_rtn_events ORDER BY id DESC LIMIT 10;</code></p>
        <p>সর্বশেষ Webhook ইভেন্ট দেখুন।</p>
      </div>
    </div>

    <!-- Flow Diagram -->
    <h2>📈 লেনদেন প্রবাহ</h2>

    <h3>PGW Callback প্রবাহ:</h3>
    <div class="highlight info">
      Portal User → Create Payment (bkash_pgw_create.php)<br>
      ↓<br>
      User → bKash Portal (pay)<br>
      ↓<br>
      bKash → Redirect to Callback (GET /api/bkash_pgw_callback.php)<br>
      ↓<br>
      Callback Handler → Execute Payment → Store in sms_inbox<br>
      ↓<br>
      Cron Job (auto_bkash_apply.php) → Match &amp; Apply → Invoice Paid<br>
      ↓<br>
      System Updated (5-10 মিনিটে)
    </div>

    <h3>RTN Webhook প্রবাহ:</h3>
    <div class="highlight info">
      bKash Payment Occurs<br>
      ↓<br>
      bKash → POST /api/bkash_rtn/notify.php (রিয়েল-টাইম)<br>
      ↓<br>
      Webhook Handler → Validate Token → Parse &amp; Store in bkash_rtn_events<br>
      ↓<br>
      Return 200 JSON Response (বাংলা)<br>
      ↓<br>
      Cron Job (bkash_rtn_process.php) → Process Events → Apply Payments<br>
      ↓<br>
      System Updated (1-5 মিনিটে)
    </div>

    <!-- Response Codes -->
    <h2>HTTP স্ট্যাটাস কোড</h2>
    <table>
      <tr>
        <th>কোড</th>
        <th>অর্থ</th>
        <th>বিবরণ</th>
      </tr>
      <tr style="background: #d4edda;">
        <td><code>200</code></td>
        <td>OK</td>
        <td>Webhook সফলভাবে গ্রহণ করা হয়েছে</td>
      </tr>
      <tr style="background: #fff3cd;">
        <td><code>405</code></td>
        <td>Method Not Allowed</td>
        <td>শুধুমাত্র POST গ্রহণ করা হয়</td>
      </tr>
      <tr style="background: #f8d7da;">
        <td><code>401</code></td>
        <td>Unauthorized</td>
        <td>টোকেন ভুল বা পাওয়া যায়নি</td>
      </tr>
      <tr style="background: #f8d7da;">
        <td><code>422</code></td>
        <td>Unprocessable Entity</td>
        <td>JSON পেলোড অবৈধ বা ফাঁকা</td>
      </tr>
      <tr style="background: #f8d7da;">
        <td><code>500</code></td>
        <td>Internal Server Error</td>
        <td>ডেটাবেস বা সিস্টেম ত্রুটি</td>
      </tr>
    </table>

    <!-- Database Tables -->
    <h2>🗄️ ডাটাবেস টেবিল</h2>

    <h3>sms_inbox (PGW পেমেন্ট)</h3>
    <table>
      <tr>
        <th>কলাম</th>
        <th>বিবরণ</th>
      </tr>
      <tr>
        <td><code>gateway</code></td>
        <td><code>'bkash_webhook'</code></td>
      </tr>
      <tr>
        <td><code>trx_id</code></td>
        <td>bKash Transaction ID</td>
      </tr>
      <tr>
        <td><code>amount</code></td>
        <td>পেমেন্ট পরিমাণ</td>
      </tr>
      <tr>
        <td><code>ref_code</code></td>
        <td>ইনভয়েস নম্বর</td>
      </tr>
      <tr>
        <td><code>raw_body</code></td>
        <td>সম্পূর্ণ JSON রেসপন্স</td>
      </tr>
    </table>

    <h3>bkash_rtn_events (RTN ওয়েবহুক)</h3>
    <table>
      <tr>
        <th>কলাম</th>
        <th>বিবরণ</th>
      </tr>
      <tr>
        <td><code>event_hash</code></td>
        <td>বিজনতা শনাক্তকরণ (SHA256)</td>
      </tr>
      <tr>
        <td><code>trx_id</code></td>
        <td>লেনদেন আইডি</td>
      </tr>
      <tr>
        <td><code>amount</code></td>
        <td>পরিমাণ</td>
      </tr>
      <tr>
        <td><code>processed</code></td>
        <td>0=পেন্ডিং, 1=সম্পন্ন</td>
      </tr>
      <tr>
        <td><code>applied_client_id</code></td>
        <td>গ্রাহক আইডি (যদি ম্যাচ করা হয়)</td>
      </tr>
      <tr>
        <td><code>raw_body</code></td>
        <td>সম্পূর্ণ JSON পেলোড</td>
      </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
      <p>📝 সাপোর্টের জন্য: Billing → bKash → RTN Dashboard দেখুন অথবা লগ চেক করুন</p>
      <p style="margin-top: 10px;">🔒 সর্বদা HTTPS ব্যবহার করুন | টোকেন সুরক্ষিত রাখুন | লগ নিয়মিত পরীক্ষা করুন</p>
    </div>
  </div>
</body>
</html>

```

## File: BKASH_WEBHOOK_REALTIME.md

```
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

```

## File: GO_LIVE_CHECKLIST.md

```
# Go-Live Checklist (Laravel Migration)

Scope:
- Legacy থেকে Laravel cutover-এর day-of-release এবং post-release tasks
- Financial safety, API stability, এবং rollback readiness নিশ্চিত করা

Release owner:
- Date:
- Window:
- Version/tag:

## 1) Pre-Go-Live (T-7 to T-1 day)

- [ ] Production backup policy verified
- [ ] Latest DB snapshot created and validated
- [ ] `.env` and secrets verified (no placeholder values)
- [ ] Queue workers configured and healthy
- [ ] Feature flags prepared (module-wise)
- [ ] Monitoring dashboards ready (5xx, latency, queue, payment mismatch)
- [ ] Alert channels active (on-call, incident room)
- [ ] Rollback runbook dry-run completed
- [ ] P0 API parity status `MATCHED`
- [ ] UAT sign-off from operations/billing

## 2) Deployment Execution (T0)

- [ ] Maintenance communication sent (if required)
- [ ] Deploy artifact checksum/tag verified
- [ ] Config cache and route cache refreshed
- [ ] DB migration policy executed (additive-only if applicable)
- [ ] Queue restart completed
- [ ] Feature flags enabled in planned order
- [ ] Smoke tests run and passed

Smoke tests:
- [ ] Login and role-based menu access
- [ ] Client search and detail open
- [ ] Invoice create and view
- [ ] Renew flow
- [ ] Payment mark paid
- [ ] bKash webhook test event
- [ ] Router control basic action
- [ ] OLT scan trigger and status poll

## 3) Canary Rollout Plan

- [ ] Internal users only (10%)
- [ ] Monitor 30-60 minutes
- [ ] Expand to 50%
- [ ] Monitor 30-60 minutes
- [ ] Expand to 100%

Canary stop conditions:
- [ ] Payment mismatch detected
- [ ] P0 endpoint failure spike
- [ ] Error rate above threshold
- [ ] Queue backlog grows uncontrollably

## 4) Rollback Triggers

Rollback if any:
- [ ] Duplicate payment incident
- [ ] Invoice correctness issue (P0)
- [ ] Auth/permission regression for admin roles
- [ ] Critical API clients failing

Rollback actions:
- [ ] Disable Laravel feature flags for affected modules
- [ ] Route fallback to legacy handlers
- [ ] Keep payment webhook on stable path
- [ ] Incident note started with timeline

## 5) Post-Go-Live (0-24h)

- [ ] Payment reconciliation report checked
- [ ] Invoice integrity scripts executed
- [ ] API error trend compared to baseline
- [ ] Performance baseline compared (p95 latency)
- [ ] Critical log anomalies triaged
- [ ] Stakeholder update sent

## 6) Post-Go-Live (24-72h)

- [ ] Customer-impact defects review
- [ ] Patch release if needed
- [ ] Legacy fallback routes usage analyzed
- [ ] Cleanup tasks and technical debt log updated
- [ ] Decommission plan draft for fully migrated modules

## 7) Command Center Contacts

- Incident commander:
- Backend lead:
- Frontend lead:
- DevOps lead:
- QA lead:
- Billing/Finance approver:

## 8) Release Sign-off

- QA sign-off: Name / Date
- Tech lead sign-off: Name / Date
- Operations sign-off: Name / Date
- Finance/Billing sign-off: Name / Date
- Final go-live approval: Name / Date

```

## File: LARAVEL_MIGRATION_ROADMAP.md

```
# ISP Billing Laravel Migration Roadmap

## 1) Goal and Success Criteria

এই migration-এর মূল লক্ষ্য:
- বর্তমান `/var/www/isp_billing` প্রজেক্টকে Laravel-এ ধাপে ধাপে migrate করা
- Existing backend business logic (billing, invoice, renew, payment, router/OLT control) intact রাখা
- Existing frontend behavior এবং admin UI consistency বজায় রাখা
- API contract break না করে cutover সম্পন্ন করা

Success criteria:
- Critical workflows parity: `client create`, `renew`, `invoice create`, `payment mark paid`, `client suspend/restore`
- API parity: legacy endpoint-এর key response fields unchanged
- Payment safety: double charge বা missing payment reconciliation না হওয়া
- Operational safety: cutover window-এ rollback readiness

## 2) Recommended Target Stack

- Framework: Laravel 11
- Language/runtime: PHP 8.3
- Database: Existing MySQL schema (initial phase)
- Cache/Queue: Redis
- Web: Nginx + PHP-FPM
- Process: Supervisor (queue workers)
- Optional: Docker deployment for repeatable environment

## 3) Migration Strategy

Strategy: **Strangler Fig Pattern** (module-by-module cutover)
- Legacy app parallel চলবে
- নতুন Laravel routes নির্দিষ্ট module-এ gradually enable হবে
- Feature flags দিয়ে controlled release হবে

Why this strategy:
- একবারে full rewrite risk কমানো
- downtime কমানো
- user-facing regression দ্রুত isolate করা

## 4) Current Codebase Mapping (Legacy -> Laravel)

### Core app files
- `app/auth.php`, `app/require_login.php`, `app/acl.php`, `app/roles.php` -> `app/Http/Middleware/*`, `Policies`, `Spatie Permission`
- `app/billing_helpers.php`, `app/invoice_calc.php` -> `app/Services/Billing/*`
- `app/mikrotik.php`, `app/mikrotik_client.php` -> `app/Services/Network/MikrotikService.php`
- `app/bkash.php`, `app/bkash_tokenized.php`, `app/bkash_rtn.php` -> `app/Services/Payment/BkashService.php`

### API files
- `api/client_*.php` -> `app/Http/Controllers/Api/ClientController.php` (+ dedicated methods)
- `api/invoice_*.php`, `api/renew.php` -> `InvoiceController`, `RenewController`
- `api/payment_mark_paid.php`, `api/bkash_*` -> `PaymentController`, `BkashWebhookController`
- `api/pon_scan.php`, `api/sfp_scan.php`, `api/olt_*` -> `OltController` + queue jobs

### Frontend templates
- Legacy page fragments -> `resources/views/*` (Blade)
- Existing `assets/*` CSS/JS প্রথমে reuse
- UI consistency reference: shared header/footer shell বজায় রাখতে হবে

## 5) Phased Plan and Timeline

## Phase 0: Discovery and Freeze (2-3 days)
- Route inventory: সব page + API + auth requirement
- DB inventory: tables, PK/FK, indexes, high-write tables
- Secret/config inventory: payment keys, router creds, SMS creds
- Contract freeze: critical API payload shape lock

Deliverables:
- Route inventory sheet
- DB dependency map
- Risk register v1

## Phase 1: Laravel Foundation (3-4 days)
- Fresh Laravel app bootstrap
- Env config (`DB`, `CACHE`, `QUEUE`, `MAIL`, `LOGGING`)
- Auth seed: users/roles/permissions base
- Global middleware: auth, permission, csrf, audit context

Deliverables:
- Running Laravel skeleton
- Health check endpoint
- Base middleware and permission scaffolding

## Phase 2: Data Layer and Shared Services (4-6 days)
- Existing schema-তে Eloquent model map
- Repository/service layer extraction
- Billing calculation helper parity tests

Deliverables:
- Models + relationships baseline
- Billing service parity report

## Phase 3: Core Billing Modules (2-3 weeks)
- Client module
- Package/Profile module
- Invoice + Renew module
- Payment/Bkash module

Deliverables:
- API compatibility layer
- UAT pass report for core billing flows

## Phase 4: Network/OLT/Router Integrations (1-2 weeks)
- Mikrotik actions as service classes
- OLT scans/long ops -> queued jobs
- Retry policy + failure notification

Deliverables:
- Network action logs
- Timeout-safe job architecture

## Phase 5: Frontend Stabilization (1-2 weeks)
- Blade migration page by page
- Layout shell standardization
- Form/request validation harmonization

Deliverables:
- Legacy parity visual checklist
- Admin UI consistency sign-off

## Phase 6: Cutover and Hardening (4-7 days)
- Canary release (10%, then 50%, then 100%)
- Monitoring: errors, payment mismatch, latency
- Rollback rehearsal and switch plan

Deliverables:
- Go-live checklist signed
- Post-release incident playbook

## 6) API Compatibility Plan

Non-negotiables:
- Existing API paths যতদিন দরকার alias করে রাখতে হবে
- Response JSON keys rename করা যাবে না (first cut)
- HTTP status code behavior compatible রাখতে হবে

Execution:
- Route adapter তৈরি (`legacy endpoint` -> `Laravel controller`)
- Snapshot tests for known API responses
- Contract diff report in CI

## 7) Database Plan

Initial policy:
- প্রথম release cycle-এ breaking schema change নয়
- Additive change only (new index, nullable column) when needed

Safety:
- Daily logical backup + pre-deploy snapshot
- Payment tables and invoice tables extra validation scripts

## 8) Test and Quality Gate

Test pyramid:
- Unit: billing calc, package pricing, date expiry logic
- Integration: invoice create/mark paid/renew workflows
- Contract: API response fields and status codes
- E2E/UAT: admin critical operations

Gate before production:
- P0 bug = 0
- Payment reconciliation mismatch = 0
- Core route success rate >= 99%

## 9) Security and Compliance

- CSRF, auth session hardening, rate limiting
- Secrets from `.env`, no key in repo
- Audit trail on financial and network control actions
- Access control by role-based permissions

## 10) Cutover and Rollback Plan

Cutover steps:
- Low-traffic window নির্বাচন
- Background jobs drain/monitor
- Feature flags মাধ্যমে module enable

Rollback steps:
- Problematic modules only fallback to legacy
- Full rollback switch প্রস্তুত রাখা
- Incident note + root cause timeline লিখে রাখা

## 11) Team Structure and Ownership

- Tech lead: architecture, risk, release approvals
- Backend team: service extraction, API parity
- Frontend team: Blade migration, UI parity
- QA/UAT: golden dataset validation
- DevOps: CI/CD, backup, monitoring, rollback scripts

## 12) Immediate 7-Day Execution Plan

Day 1:
- Route inventory, API inventory, DB critical path list

Day 2:
- Laravel setup + env + auth baseline

Day 3:
- Client module models/services শুরু

Day 4:
- Invoice and renew service extraction

Day 5:
- Payment/Bkash webhook compatibility tests

Day 6:
- First canary for internal users

Day 7:
- Defect fix + go/no-go checkpoint

## 13) Definition of Done (Project)

- সব critical module Laravel-এ running
- Legacy routes only compatibility wrapper হিসেবে আছে
- Performance acceptable এবং payment safe
- Team runbook + handover documentation complete

```

## File: LARAVEL_MODULE_CHECKLIST.md

```
# Laravel Migration Module-by-Module Checklist

এই checklist execution tracking-এর জন্য।
স্ট্যাটাস মান: `[ ]` Not started, `[~]` In progress, `[x]` Done

## 1) Foundation and Governance

- [ ] Migration branch strategy finalized
- [ ] Environment parity (dev/stage/prod) documented
- [ ] Backup and restore drill completed
- [ ] Feature flag framework selected
- [ ] Monitoring baseline dashboards created
- [ ] Rollback SOP approved

## 2) Auth, ACL, Session

Legacy refs:
- `app/auth.php`
- `app/require_login.php`
- `app/acl.php`
- `app/roles.php`
- `app/session_track.php`

Checklist:
- [ ] Laravel auth implemented
- [ ] Roles and permissions mapped 1:1
- [ ] Unauthorized access behavior parity validated
- [ ] Session timeout policy matched with legacy
- [ ] Audit log for login/logout/admin actions enabled

Acceptance:
- [ ] Same user role -> same menu/data access
- [ ] No privilege escalation path found in QA

## 3) Client Management Module

Legacy refs:
- `api/client_change_package.php`
- `api/client_expiry_update.php`
- `api/client_live_status.php`
- `api/client_meta.php`
- `api/client_restore.php`
- `api/client_status.php`
- `api/suggest_clients.php`

Checklist:
- [ ] Client model and relations verified
- [ ] Client search and suggestion API parity maintained
- [ ] Change package flow migrated
- [ ] Expiry update flow migrated
- [ ] Restore/suspend status flow migrated
- [ ] Live status and last logout data parity verified

Acceptance:
- [ ] Client detail page diff-free on key fields
- [ ] Status change reflected in both UI and API

## 4) Package and Profile Module

Legacy refs:
- `api/package_bulk_import.php`
- `api/package_delete.php`
- `api/package_upsert.php`
- `app/package_profile.php`
- `app/ppp_profiles.php`
- `api/ppp_profiles.php`

Checklist:
- [ ] Package CRUD migrated
- [ ] Bulk import validation rules matched
- [ ] PPP profile listing and sync behavior matched
- [ ] Package pricing lookup parity (`api/get_package_price.php`) ensured

Acceptance:
- [ ] Same package assignment result for same input
- [ ] Bulk import rejects invalid rows like legacy

## 5) Invoice and Billing Module

Legacy refs:
- `api/invoice_create.php`
- `api/invoice_mark_paid.php`
- `api/invoice_quick_renew.php`
- `api/renew.php`
- `app/invoice_calc.php`
- `app/billing_helpers.php`

Checklist:
- [ ] Billing calculation service extracted and unit-tested
- [ ] Invoice create endpoint parity completed
- [ ] Mark paid flow migrated with audit log
- [ ] Quick renew flow migrated
- [ ] Expiry date calculation parity validated
- [ ] Duplicate invoice guard implemented

Acceptance:
- [ ] Golden dataset total amount 100% parity
- [ ] Renew workflow regression test passed

## 6) Payment and bKash Module

Legacy refs:
- `api/bkash_pgw_callback.php`
- `api/bkash_webhook.php`
- `api/sms_bkash.php`
- `app/bkash.php`
- `app/bkash_tokenized.php`
- `app/bkash_rtn.php`

Checklist:
- [ ] bKash callback signature verification implemented
- [ ] Webhook idempotency key handling done
- [ ] Payment mark paid path with reconciliation done
- [ ] Failed callback retry strategy implemented
- [ ] Payment audit and trace IDs enabled

Acceptance:
- [ ] No duplicate credit on repeated webhook
- [ ] Reconciliation report mismatch = 0

## 7) Router and Network Control Module

Legacy refs:
- `app/mikrotik.php`
- `app/mikrotik_client.php`
- `api/control.php`
- `api/bulk_control.php`
- `api/router_ids_by_clients.php`
- `api/router_mac_audit.php`

Checklist:
- [ ] Mikrotik service wrapper implemented
- [ ] Single client control migrated
- [ ] Bulk control migrated with queue support
- [ ] Timeout and retry policy set
- [ ] Error handling standardized (user-safe message + internal details)

Acceptance:
- [ ] Control action success/failure parity with legacy
- [ ] No blocking request > timeout threshold

## 8) OLT and PON Module

Legacy refs:
- `api/pon_scan.php`
- `api/sfp_scan.php`
- `api/olt_test_connection.php`
- `api/olt_mac_refresh_telnet.php`
- `app/olt_auto_link.php`
- `app/olt_schema.php`

Checklist:
- [ ] OLT connection test endpoint migrated
- [ ] PON/SFP scan as queue jobs implemented
- [ ] MAC refresh workflow migrated
- [ ] Auto-link logic ported and verified
- [ ] Long-running task status endpoint created

Acceptance:
- [ ] Scan results accuracy parity confirmed
- [ ] No UI freeze for long-running scans

## 9) Notifications and Messaging

Legacy refs:
- `app/notify.php`
- `app/sms.php`
- `api/bulk_notify.php`

Checklist:
- [ ] SMS provider abstraction created
- [ ] Bulk notify queue-based processing enabled
- [ ] Failed send retry and dead-letter handling set
- [ ] Notification audit records maintained

Acceptance:
- [ ] Delivery report parity with legacy
- [ ] Bulk send does not block user requests

## 10) Dashboard, Reports, and Utilities

Legacy refs:
- `app/dashboard_service.php`
- `reports/`
- `api/traffic_graph.php`
- `api/quick_search.php`

Checklist:
- [ ] Dashboard queries optimized and migrated
- [ ] Report exports migrated and validated
- [ ] Traffic graph data API parity verified
- [ ] Quick search performance benchmark completed

Acceptance:
- [ ] Dashboard load time within target SLA
- [ ] Top reports produce identical totals

## 11) Frontend Migration (Blade + Existing Assets)

Checklist:
- [ ] Shared layout shell (`header/footer/sidebar`) standardized
- [ ] Legacy CSS/JS reused without visual regression
- [ ] Form components and validation message parity maintained
- [ ] AJAX page fragments post-render hook standardized
- [ ] Mobile responsiveness sanity test completed

Acceptance:
- [ ] Visual parity checklist pass rate >= 95%
- [ ] No critical navigation break in admin panel

## 12) API Compatibility and Versioning

Checklist:
- [ ] Legacy API routes aliased to Laravel controllers
- [ ] JSON field-level contract tests added
- [ ] Status code parity tests added
- [ ] Deprecation headers planned for future cleanup

Acceptance:
- [ ] Existing clients require no immediate code change

## 13) Data Integrity and Migration Safety

Checklist:
- [ ] Pre-cutover DB snapshot automated
- [ ] Post-cutover data validation scripts ready
- [ ] Payment/invoice integrity scripts scheduled
- [ ] Failed transaction alerting configured

Acceptance:
- [ ] Financial integrity checks passed every deploy

## 14) QA, UAT, and Release Gates

Checklist:
- [ ] Unit test baseline coverage agreed
- [ ] Integration tests for critical workflows complete
- [ ] UAT scripts approved by operations/billing teams
- [ ] Canary rollout criteria documented
- [ ] Go/No-Go checklist signed by stakeholders

Acceptance:
- [ ] P0/P1 bug policy satisfied before production rollout

## 15) Cutover Execution Checklist

- [ ] Traffic window approved
- [ ] Queue workers healthy
- [ ] Feature flags prepared by module
- [ ] Monitoring and alert channels active
- [ ] On-call roster assigned
- [ ] Rollback switch tested
- [ ] Final smoke test completed

## 16) Post-Go-Live Checklist

- [ ] 24h incident monitoring completed
- [ ] Payment reconciliation report verified
- [ ] API error rate compared against baseline
- [ ] Performance tuning items logged
- [ ] Legacy decommission plan drafted

## 17) Suggested Execution Order (Priority)

1. Foundation + Auth/ACL
2. Client Management
3. Package/Profile
4. Invoice/Billing
5. Payment/bKash
6. Router Control
7. OLT/PON
8. Dashboard/Reports
9. Frontend parity polish
10. Final cutover and hardening

```

## File: ROUTE_INVENTORY_PREFILLED.md

```
]633;E;{   echo '# Route Inventory (Pre-filled Draft)'\x3b   echo ''\x3b   echo 'Source snapshot date: 2026-03-09'\x3b   echo 'Scope:'\x3b   echo '- Root pages: `/*.php`'\x3b   echo '- API endpoints: `/api/*.php`'\x3b   echo '- Public pages: `/public/*.php`'\x3b   echo ''\x3b   echo 'Notes:'\x3b   echo '- `Method` field is heuristic for API routes (detected from code pattern), verify manually.'\x3b   echo '- `Auth`, `Role/Permission`, and `Owner` are intentionally `TBD` pending team assignment.'\x3b   echo ''\x3b   echo '## Legend'\x3b   echo ''\x3b   echo '- Route Type: `PAGE`, `API`, `AJAX`, `WEBHOOK`, `CRON`'\x3b   echo '- Auth: `PUBLIC`, `LOGIN_REQUIRED`, `ROLE_BASED`, `TBD`'\x3b   echo '- Priority: `P0`, `P1`, `P2`'\x3b   echo '- Migration Status: `NOT_STARTED`, `IN_PROGRESS`, `DONE`, `DEFERRED`'\x3b   echo '- Parity Status: `NOT_TESTED`, `MATCHED`, `MISMATCH`'\x3b   echo ''\x3b   echo '## Inventory Table'\x3b   echo ''\x3b   echo '| ID | Legacy Path | Route Type | Method | Module | Auth | Role/Permission | Input Params | Output Type | Used By (UI/App/Cron) | Dependency (DB/API/Service) | Priority | Owner | Laravel Target Controller@Method | Feature Flag | Migration Status | Parity Status | Notes |'\x3b   echo '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|'\x3b } > "$outfile";fb83547b-5e7b-4e91-bdb7-32c0ce4cdb6b]633;C# Route Inventory (Pre-filled Draft)

Source snapshot date: 2026-03-09
Scope:
- Root pages: `/*.php`
- API endpoints: `/api/*.php`
- Public pages: `/public/*.php`

Notes:
- `Method` field is heuristic for API routes (detected from code pattern), verify manually.
- `Auth`, `Role/Permission`, and `Owner` are intentionally `TBD` pending team assignment.

## Legend

- Route Type: `PAGE`, `API`, `AJAX`, `WEBHOOK`, `CRON`
- Auth: `PUBLIC`, `LOGIN_REQUIRED`, `ROLE_BASED`, `TBD`
- Priority: `P0`, `P1`, `P2`
- Migration Status: `NOT_STARTED`, `IN_PROGRESS`, `DONE`, `DEFERRED`
- Parity Status: `NOT_TESTED`, `MATCHED`, `MISMATCH`

## Inventory Table

| ID | Legacy Path | Route Type | Method | Module | Auth | Role/Permission | Input Params | Output Type | Used By (UI/App/Cron) | Dependency (DB/API/Service) | Priority | Owner | Laravel Target Controller@Method | Feature Flag | Migration Status | Parity Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `/index.php` | PAGE | GET | Dashboard | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\dashboardController@index` | `ff_web_dashboard` | NOT_STARTED | NOT_TESTED | |
| 1 | `/api/auto_control_client.php` | API | GET/POST | General | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\GeneralController@auto_control_client` | `ff_api_auto_control_client` | NOT_STARTED | NOT_TESTED | |
| 2 | `/api/auto_link_onu_from_mt.php` | API | GET | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@auto_link_onu_from_mt` | `ff_api_auto_link_onu_from_mt` | NOT_STARTED | NOT_TESTED | |
| 3 | `/api/bkash_pgw_callback.php` | WEBHOOK | GET | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@bkash_pgw_callback` | `ff_api_bkash_pgw_callback` | NOT_STARTED | NOT_TESTED | |
| 4 | `/api/bkash_webhook.php` | WEBHOOK | GET/POST | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@bkash_webhook` | `ff_api_bkash_webhook` | NOT_STARTED | NOT_TESTED | |
| 5 | `/api/bulk_control.php` | API | POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@bulk_control` | `ff_api_bulk_control` | NOT_STARTED | NOT_TESTED | |
| 6 | `/api/bulk_notify.php` | API | POST | Utility/Support | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\UtilitySupportController@bulk_notify` | `ff_api_bulk_notify` | NOT_STARTED | NOT_TESTED | |
| 7 | `/api/bulk_profile.php` | API | POST | Package/Profile | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\PackageProfileController@bulk_profile` | `ff_api_bulk_profile` | NOT_STARTED | NOT_TESTED | |
| 8 | `/api/check_unique.php` | API | GET | Utility/Support | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\UtilitySupportController@check_unique` | `ff_api_check_unique` | NOT_STARTED | NOT_TESTED | |
| 9 | `/api/client_change_package.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_change_package` | `ff_api_client_change_package` | NOT_STARTED | NOT_TESTED | |
| 10 | `/api/client_expiry_update.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_expiry_update` | `ff_api_client_expiry_update` | NOT_STARTED | NOT_TESTED | |
| 11 | `/api/client_last_logout.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_last_logout` | `ff_api_client_last_logout` | NOT_STARTED | NOT_TESTED | |
| 12 | `/api/client_left_bulk.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_left_bulk` | `ff_api_client_left_bulk` | NOT_STARTED | NOT_TESTED | |
| 13 | `/api/client_left_toggle.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_left_toggle` | `ff_api_client_left_toggle` | NOT_STARTED | NOT_TESTED | |
| 14 | `/api/client_live_status.php` | API | GET/POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_live_status` | `ff_api_client_live_status` | NOT_STARTED | NOT_TESTED | |
| 15 | `/api/client_meta.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_meta` | `ff_api_client_meta` | NOT_STARTED | NOT_TESTED | |
| 16 | `/api/client_restore.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_restore` | `ff_api_client_restore` | NOT_STARTED | NOT_TESTED | |
| 17 | `/api/client_status.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_status` | `ff_api_client_status` | NOT_STARTED | NOT_TESTED | |
| 18 | `/api/control.php` | API | POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@control` | `ff_api_control` | NOT_STARTED | NOT_TESTED | |
| 19 | `/api/get_package_price.php` | API | GET | Package/Profile | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\PackageProfileController@get_package_price` | `ff_api_get_package_price` | NOT_STARTED | NOT_TESTED | |
| 20 | `/api/invoice_create.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@invoice_create` | `ff_api_invoice_create` | NOT_STARTED | NOT_TESTED | |
| 21 | `/api/invoice_mark_paid.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@invoice_mark_paid` | `ff_api_invoice_mark_paid` | NOT_STARTED | NOT_TESTED | |
| 22 | `/api/invoice_quick_renew.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@invoice_quick_renew` | `ff_api_invoice_quick_renew` | NOT_STARTED | NOT_TESTED | |
| 23 | `/api/link_all.php` | API | POST | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@link_all` | `ff_api_link_all` | NOT_STARTED | NOT_TESTED | |
| 24 | `/api/link_by_mac.php` | API | GET/POST | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@link_by_mac` | `ff_api_link_by_mac` | NOT_STARTED | NOT_TESTED | |
| 25 | `/api/mac_lookup_csv.php` | API | GET/POST | Utility/Support | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\UtilitySupportController@mac_lookup_csv` | `ff_api_mac_lookup_csv` | NOT_STARTED | NOT_TESTED | |
| 26 | `/api/mac_vendor.php` | API | GET/POST | Utility/Support | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\UtilitySupportController@mac_vendor` | `ff_api_mac_vendor` | NOT_STARTED | NOT_TESTED | |
| 27 | `/api/mt_do_something.php` | API | GET/POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@mt_do_something` | `ff_api_mt_do_something` | NOT_STARTED | NOT_TESTED | |
| 28 | `/api/mt_dump_sample.php` | API | GET | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@mt_dump_sample` | `ff_api_mt_dump_sample` | NOT_STARTED | NOT_TESTED | |
| 29 | `/api/mt_import_clients.php` | API | POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@mt_import_clients` | `ff_api_mt_import_clients` | NOT_STARTED | NOT_TESTED | |
| 30 | `/api/mt_list_profiles.php` | API | GET | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@mt_list_profiles` | `ff_api_mt_list_profiles` | NOT_STARTED | NOT_TESTED | |
| 31 | `/api/mt_list_secrets.php` | API | GET | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@mt_list_secrets` | `ff_api_mt_list_secrets` | NOT_STARTED | NOT_TESTED | |
| 32 | `/api/mt_sync_clients.php` | API | GET/POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@mt_sync_clients` | `ff_api_mt_sync_clients` | NOT_STARTED | NOT_TESTED | |
| 33 | `/api/olt_mac_refresh_telnet.php` | API | GET/POST | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@olt_mac_refresh_telnet` | `ff_api_olt_mac_refresh_telnet` | NOT_STARTED | NOT_TESTED | |
| 34 | `/api/olt_test_connection.php` | API | POST | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@olt_test_connection` | `ff_api_olt_test_connection` | NOT_STARTED | NOT_TESTED | |
| 35 | `/api/package_bulk_import.php` | API | POST | Package/Profile | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\PackageProfileController@package_bulk_import` | `ff_api_package_bulk_import` | NOT_STARTED | NOT_TESTED | |
| 36 | `/api/package_delete.php` | API | POST | Package/Profile | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\PackageProfileController@package_delete` | `ff_api_package_delete` | NOT_STARTED | NOT_TESTED | |
| 37 | `/api/package_upsert.php` | API | POST | Package/Profile | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\PackageProfileController@package_upsert` | `ff_api_package_upsert` | NOT_STARTED | NOT_TESTED | |
| 38 | `/api/payment_mark_paid.php` | WEBHOOK | POST | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@payment_mark_paid` | `ff_api_payment_mark_paid` | NOT_STARTED | NOT_TESTED | |
| 39 | `/api/pon_scan.php` | API | GET | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@pon_scan` | `ff_api_pon_scan` | NOT_STARTED | NOT_TESTED | |
| 40 | `/api/portal_require_login.php` | API | POST | Utility/Support | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\UtilitySupportController@portal_require_login` | `ff_api_portal_require_login` | NOT_STARTED | NOT_TESTED | |
| 41 | `/api/pppoe_last_logout.php` | API | GET/POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@pppoe_last_logout` | `ff_api_pppoe_last_logout` | NOT_STARTED | NOT_TESTED | |
| 42 | `/api/pppoe_secret_check.php` | API | GET | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@pppoe_secret_check` | `ff_api_pppoe_secret_check` | NOT_STARTED | NOT_TESTED | |
| 43 | `/api/ppp_profiles.php` | API | GET/POST | Package/Profile | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\PackageProfileController@ppp_profiles` | `ff_api_ppp_profiles` | NOT_STARTED | NOT_TESTED | |
| 44 | `/api/quick_search.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@quick_search` | `ff_api_quick_search` | NOT_STARTED | NOT_TESTED | |
| 45 | `/api/renew.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@renew` | `ff_api_renew` | NOT_STARTED | NOT_TESTED | |
| 46 | `/api/router_ids_by_clients.php` | API | GET/POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@router_ids_by_clients` | `ff_api_router_ids_by_clients` | NOT_STARTED | NOT_TESTED | |
| 47 | `/api/router_mac_audit.php` | API | GET/POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@router_mac_audit` | `ff_api_router_mac_audit` | NOT_STARTED | NOT_TESTED | |
| 48 | `/api/routers_list_simple.php` | API | POST | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@routers_list_simple` | `ff_api_routers_list_simple` | NOT_STARTED | NOT_TESTED | |
| 49 | `/api/sfp_scan.php` | API | GET | OLT/PON | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\OLTPONController@sfp_scan` | `ff_api_sfp_scan` | NOT_STARTED | NOT_TESTED | |
| 50 | `/api/sms_bkash.php` | WEBHOOK | POST | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@sms_bkash` | `ff_api_sms_bkash` | NOT_STARTED | NOT_TESTED | |
| 51 | `/api/ssh_probe.php` | API | GET | Network/Router | TBD | TBD | TBD | JSON | UI/App | DB/Service | P1 | TBD | `Api\NetworkRouterController@ssh_probe` | `ff_api_ssh_probe` | NOT_STARTED | NOT_TESTED | |
| 52 | `/api/suggest_clients.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@suggest_clients` | `ff_api_suggest_clients` | NOT_STARTED | NOT_TESTED | |
| 53 | `/api/traffic_graph.php` | API | GET | Utility/Support | TBD | TBD | TBD | JSON | UI/App | DB/Service | P2 | TBD | `Api\UtilitySupportController@traffic_graph` | `ff_api_traffic_graph` | NOT_STARTED | NOT_TESTED | |
| 54 | `/api/update_expiry.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@update_expiry` | `ff_api_update_expiry` | NOT_STARTED | NOT_TESTED | |
| 1 | `/public/403.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_403` | NOT_STARTED | NOT_TESTED | |
| 2 | `/public/accounts_link_action.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_accounts_link_action` | NOT_STARTED | NOT_TESTED | |
| 3 | `/public/accounts_manage.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_accounts_manage` | NOT_STARTED | NOT_TESTED | |
| 4 | `/public/accounts.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_accounts` | NOT_STARTED | NOT_TESTED | |
| 5 | `/public/admin_tools.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_admin_tools` | NOT_STARTED | NOT_TESTED | |
| 6 | `/public/all_clientt_info.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_all_clientt_info` | NOT_STARTED | NOT_TESTED | |
| 7 | `/public/areas_geo.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_areas_geo` | NOT_STARTED | NOT_TESTED | |
| 8 | `/public/audit_logs.legacy.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_audit_logs.legacy` | NOT_STARTED | NOT_TESTED | |
| 9 | `/public/audit_logs.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_audit_logs` | NOT_STARTED | NOT_TESTED | |
| 10 | `/public/auto_suspend_dashboard.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_auto_suspend_dashboard` | NOT_STARTED | NOT_TESTED | |
| 11 | `/public/billing_discount_api.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_billing_discount_api` | NOT_STARTED | NOT_TESTED | |
| 12 | `/public/billing.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_billing` | NOT_STARTED | NOT_TESTED | |
| 13 | `/public/bkash_inbox.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_bkash_inbox` | NOT_STARTED | NOT_TESTED | |
| 14 | `/public/bkash_rtn_dashboard.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_bkash_rtn_dashboard` | NOT_STARTED | NOT_TESTED | |
| 15 | `/public/bkash_sms_tester.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_bkash_sms_tester` | NOT_STARTED | NOT_TESTED | |
| 16 | `/public/client_add.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_add` | NOT_STARTED | NOT_TESTED | |
| 17 | `/public/client_edit.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_edit` | NOT_STARTED | NOT_TESTED | |
| 18 | `/public/client_geo_bulk.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_geo_bulk` | NOT_STARTED | NOT_TESTED | |
| 19 | `/public/client_geo_picker.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_geo_picker` | NOT_STARTED | NOT_TESTED | |
| 20 | `/public/client_geo_save.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_geo_save` | NOT_STARTED | NOT_TESTED | |
| 21 | `/public/client_invoices.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_invoices` | NOT_STARTED | NOT_TESTED | |
| 22 | `/public/client_ledger_balance_update.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_ledger_balance_update` | NOT_STARTED | NOT_TESTED | |
| 23 | `/public/client_ledger - Copy.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_ledger - Copy` | NOT_STARTED | NOT_TESTED | |
| 24 | `/public/client_ledger.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_ledger` | NOT_STARTED | NOT_TESTED | |
| 25 | `/public/client_list_by_status.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_list_by_status` | NOT_STARTED | NOT_TESTED | |
| 26 | `/public/client_live_graph.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_live_graph` | NOT_STARTED | NOT_TESTED | |
| 27 | `/public/client_payment_add_query.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_payment_add_query` | NOT_STARTED | NOT_TESTED | |
| 28 | `/public/client_payment_delete.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_payment_delete` | NOT_STARTED | NOT_TESTED | |
| 29 | `/public/client_payments.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_payments` | NOT_STARTED | NOT_TESTED | |
| 30 | `/public/clients_offline.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients_offline` | NOT_STARTED | NOT_TESTED | |
| 31 | `/public/clients_online.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients_online` | NOT_STARTED | NOT_TESTED | |
| 32 | `/public/clients.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients` | NOT_STARTED | NOT_TESTED | |
| 33 | `/public/clients_search.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients_search` | NOT_STARTED | NOT_TESTED | |
| 34 | `/public/client_status.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_status` | NOT_STARTED | NOT_TESTED | |
| 35 | `/public/client_view.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_view` | NOT_STARTED | NOT_TESTED | |
| 36 | `/public/client_whitelist.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_whitelist` | NOT_STARTED | NOT_TESTED | |
| 37 | `/public/collections.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_collections` | NOT_STARTED | NOT_TESTED | |
| 38 | `/public/cron_dashboard.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_cron_dashboard` | NOT_STARTED | NOT_TESTED | |
| 39 | `/public/deleted_clients.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_deleted_clients` | NOT_STARTED | NOT_TESTED | |
| 40 | `/public/dev_mail_test.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_dev_mail_test` | NOT_STARTED | NOT_TESTED | |
| 41 | `/public/due_analytics.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_due_analytics` | NOT_STARTED | NOT_TESTED | |
| 42 | `/public/due_report.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_due_report` | NOT_STARTED | NOT_TESTED | |
| 43 | `/public/due_report_pro.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_due_report_pro` | NOT_STARTED | NOT_TESTED | |
| 44 | `/public/expense_accounts.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expense_accounts` | NOT_STARTED | NOT_TESTED | |
| 45 | `/public/expense_add.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expense_add` | NOT_STARTED | NOT_TESTED | |
| 46 | `/public/expense_categories.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expense_categories` | NOT_STARTED | NOT_TESTED | |
| 47 | `/public/expense_delete.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expense_delete` | NOT_STARTED | NOT_TESTED | |
| 48 | `/public/expense_edit.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expense_edit` | NOT_STARTED | NOT_TESTED | |
| 49 | `/public/expenses_export.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expenses_export` | NOT_STARTED | NOT_TESTED | |
| 50 | `/public/expenses.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_expenses` | NOT_STARTED | NOT_TESTED | |
| 51 | `/public/export_clients.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_export_clients` | NOT_STARTED | NOT_TESTED | |
| 52 | `/public/export_invoices.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_export_invoices` | NOT_STARTED | NOT_TESTED | |
| 53 | `/public/forgot.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_forgot` | NOT_STARTED | NOT_TESTED | |
| 54 | `/public/forgot_submit.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_forgot_submit` | NOT_STARTED | NOT_TESTED | |
| 55 | `/public/import_clients.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_import_clients` | NOT_STARTED | NOT_TESTED | |
| 56 | `/public/import_mikrotik_client.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_import_mikrotik_client` | NOT_STARTED | NOT_TESTED | |
| 57 | `/public/income_expense2.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_income_expense2` | NOT_STARTED | NOT_TESTED | |
| 58 | `/public/income_expense_export.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_income_expense_export` | NOT_STARTED | NOT_TESTED | |
| 59 | `/public/income_expense.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_income_expense` | NOT_STARTED | NOT_TESTED | |
| 60 | `/public/index.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_index` | NOT_STARTED | NOT_TESTED | |
| 61 | `/public/invoice_amount_update.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_amount_update` | NOT_STARTED | NOT_TESTED | |
| 62 | `/public/invoice_delete.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_delete` | NOT_STARTED | NOT_TESTED | |
| 63 | `/public/invoice_generate.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_generate` | NOT_STARTED | NOT_TESTED | |
| 64 | `/public/invoice_list.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_list` | NOT_STARTED | NOT_TESTED | |
| 65 | `/public/invoice_new.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_new` | NOT_STARTED | NOT_TESTED | |
| 66 | `/public/invoice_pdf.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_pdf` | NOT_STARTED | NOT_TESTED | |
| 67 | `/public/invoice_pdf_template.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_pdf_template` | NOT_STARTED | NOT_TESTED | |
| 68 | `/public/invoice_print.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_print` | NOT_STARTED | NOT_TESTED | |
| 69 | `/public/invoices.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoices` | NOT_STARTED | NOT_TESTED | |
| 70 | `/public/invoice_view.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_view` | NOT_STARTED | NOT_TESTED | |
| 71 | `/public/login_activity.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_login_activity` | NOT_STARTED | NOT_TESTED | |
| 72 | `/public/login.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_login` | NOT_STARTED | NOT_TESTED | |
| 73 | `/public/logout.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_logout` | NOT_STARTED | NOT_TESTED | |
| 74 | `/public/migrate_user_wallets.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_migrate_user_wallets` | NOT_STARTED | NOT_TESTED | |
| 75 | `/public/migrate_wallet_approvals.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_migrate_wallet_approvals` | NOT_STARTED | NOT_TESTED | |
| 76 | `/public/network_map_clients.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_network_map_clients` | NOT_STARTED | NOT_TESTED | |
| 77 | `/public/network_map.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_network_map` | NOT_STARTED | NOT_TESTED | |
| 78 | `/public/notifications.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_notifications` | NOT_STARTED | NOT_TESTED | |
| 79 | `/public/olt_logs_view.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_olt_logs_view` | NOT_STARTED | NOT_TESTED | |
| 80 | `/public/olt_mac_table.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_olt_mac_table` | NOT_STARTED | NOT_TESTED | |
| 81 | `/public/online_users.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_online_users` | NOT_STARTED | NOT_TESTED | |
| 82 | `/public/onu-monitor.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_onu-monitor` | NOT_STARTED | NOT_TESTED | |
| 83 | `/public/packages.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_packages` | NOT_STARTED | NOT_TESTED | |
| 84 | `/public/payment_add2.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_add2` | NOT_STARTED | NOT_TESTED | |
| 85 | `/public/payment_add.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_add` | NOT_STARTED | NOT_TESTED | |
| 86 | `/public/payment_bkash.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_bkash` | NOT_STARTED | NOT_TESTED | |
| 87 | `/public/payment_delete.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_delete` | NOT_STARTED | NOT_TESTED | |
| 88 | `/public/payment_edit.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_edit` | NOT_STARTED | NOT_TESTED | |
| 89 | `/public/payment_receipt.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_receipt` | NOT_STARTED | NOT_TESTED | |
| 90 | `/public/payment_report_export.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_report_export` | NOT_STARTED | NOT_TESTED | |
| 91 | `/public/payment_report.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_report` | NOT_STARTED | NOT_TESTED | |
| 92 | `/public/payments_add_query.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payments_add_query` | NOT_STARTED | NOT_TESTED | |
| 93 | `/public/payments_bkash_inbox.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payments_bkash_inbox` | NOT_STARTED | NOT_TESTED | |
| 94 | `/public/payments_bkash_match.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payments_bkash_match` | NOT_STARTED | NOT_TESTED | |
| 95 | `/public/permanent_delete_client.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_permanent_delete_client` | NOT_STARTED | NOT_TESTED | |
| 96 | `/public/process_manual.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_process_manual` | NOT_STARTED | NOT_TESTED | |
| 97 | `/public/profile.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_profile` | NOT_STARTED | NOT_TESTED | |
| 98 | `/public/rebuild_ledgers.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_rebuild_ledgers` | NOT_STARTED | NOT_TESTED | |
| 99 | `/public/receipt_payment.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_receipt_payment` | NOT_STARTED | NOT_TESTED | |
| 100 | `/public/register.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_register` | NOT_STARTED | NOT_TESTED | |
| 101 | `/public/register_submit.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_register_submit` | NOT_STARTED | NOT_TESTED | |
| 102 | `/public/report_expense_categories.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_report_expense_categories` | NOT_STARTED | NOT_TESTED | |
| 103 | `/public/report_package_wise_export.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_report_package_wise_export` | NOT_STARTED | NOT_TESTED | |
| 104 | `/public/report_package_wise.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_report_package_wise` | NOT_STARTED | NOT_TESTED | |
| 105 | `/public/report_payments.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_report_payments` | NOT_STARTED | NOT_TESTED | |
| 106 | `/public/reseller_add.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_reseller_add` | NOT_STARTED | NOT_TESTED | |
| 107 | `/public/reseller_add_query.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_reseller_add_query` | NOT_STARTED | NOT_TESTED | |
| 108 | `/public/reseller_packages.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_reseller_packages` | NOT_STARTED | NOT_TESTED | |
| 109 | `/public/resellers.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_resellers` | NOT_STARTED | NOT_TESTED | |
| 110 | `/public/reseller_toggle.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_reseller_toggle` | NOT_STARTED | NOT_TESTED | |
| 111 | `/public/reseller_view.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_reseller_view` | NOT_STARTED | NOT_TESTED | |
| 112 | `/public/reset.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_reset` | NOT_STARTED | NOT_TESTED | |
| 113 | `/public/restore_client.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_restore_client` | NOT_STARTED | NOT_TESTED | |
| 114 | `/public/router_add.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_router_add` | NOT_STARTED | NOT_TESTED | |
| 115 | `/public/router_edit.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_router_edit` | NOT_STARTED | NOT_TESTED | |
| 116 | `/public/router_mac_audit.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_router_mac_audit` | NOT_STARTED | NOT_TESTED | |
| 117 | `/public/router_online.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_router_online` | NOT_STARTED | NOT_TESTED | |
| 118 | `/public/router_ping.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_router_ping` | NOT_STARTED | NOT_TESTED | |
| 119 | `/public/routers.php` | PAGE | GET | Network/Operations | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\NetworkOperationsController@index` | `ff_page_routers` | NOT_STARTED | NOT_TESTED | |
| 120 | `/public/search_logic.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_search_logic` | NOT_STARTED | NOT_TESTED | |
| 121 | `/public/settings_bkash.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_settings_bkash` | NOT_STARTED | NOT_TESTED | |
| 122 | `/public/settings_company.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_settings_company` | NOT_STARTED | NOT_TESTED | |
| 123 | `/public/settings.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_settings` | NOT_STARTED | NOT_TESTED | |
| 124 | `/public/sms_gateway.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_sms_gateway` | NOT_STARTED | NOT_TESTED | |
| 125 | `/public/sms_groups.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_sms_groups` | NOT_STARTED | NOT_TESTED | |
| 126 | `/public/sms_individual.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_sms_individual` | NOT_STARTED | NOT_TESTED | |
| 127 | `/public/sms_send.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_sms_send` | NOT_STARTED | NOT_TESTED | |
| 128 | `/public/sms_templates.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_sms_templates` | NOT_STARTED | NOT_TESTED | |
| 129 | `/public/suspended_clients.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_suspended_clients` | NOT_STARTED | NOT_TESTED | |
| 130 | `/public/sync_clients_router_mac.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_sync_clients_router_mac` | NOT_STARTED | NOT_TESTED | |
| 131 | `/public/test_pdf.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_test_pdf` | NOT_STARTED | NOT_TESTED | |
| 132 | `/public/theme_set.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_theme_set` | NOT_STARTED | NOT_TESTED | |
| 133 | `/public/theme_toggle.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_theme_toggle` | NOT_STARTED | NOT_TESTED | |
| 134 | `/public/ticket_add.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_ticket_add` | NOT_STARTED | NOT_TESTED | |
| 135 | `/public/tickets.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_tickets` | NOT_STARTED | NOT_TESTED | |
| 136 | `/public/ticket_view.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_ticket_view` | NOT_STARTED | NOT_TESTED | |
| 137 | `/public/traffic_data.php` | PAGE | GET | Reports | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\ReportsController@index` | `ff_page_traffic_data` | NOT_STARTED | NOT_TESTED | |
| 138 | `/public/users_permission.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_users_permission` | NOT_STARTED | NOT_TESTED | |
| 139 | `/public/users.php` | PAGE | GET | Admin/Settings | TBD | TBD | TBD | HTML | UI | DB/Session | P1 | TBD | `Web\AdminSettingsController@index` | `ff_page_users` | NOT_STARTED | NOT_TESTED | |
| 140 | `/public/verify.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_verify` | NOT_STARTED | NOT_TESTED | |
| 141 | `/public/waiver_add.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_waiver_add` | NOT_STARTED | NOT_TESTED | |
| 142 | `/public/waivers.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_waivers` | NOT_STARTED | NOT_TESTED | |
| 143 | `/public/wallet_accounts.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_accounts` | NOT_STARTED | NOT_TESTED | |
| 144 | `/public/wallet_approvals.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_approvals` | NOT_STARTED | NOT_TESTED | |
| 145 | `/public/wallets_dashboard2.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallets_dashboard2` | NOT_STARTED | NOT_TESTED | |
| 146 | `/public/wallets_dashboard.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallets_dashboard` | NOT_STARTED | NOT_TESTED | |
| 147 | `/public/wallet_seed.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_seed` | NOT_STARTED | NOT_TESTED | |
| 148 | `/public/wallet_settlement.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_settlement` | NOT_STARTED | NOT_TESTED | |
| 149 | `/public/wallets.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallets` | NOT_STARTED | NOT_TESTED | |
| 150 | `/public/wallet_statement.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_statement` | NOT_STARTED | NOT_TESTED | |
| 151 | `/public/wallet_transfer_action.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_transfer_action` | NOT_STARTED | NOT_TESTED | |
| 152 | `/public/warning.php` | PAGE | GET | Admin/UI | TBD | TBD | TBD | HTML | UI | DB/Session | P2 | TBD | `Web\AdminUIController@index` | `ff_page_warning` | NOT_STARTED | NOT_TESTED | |
| 153 | `/public/webhook_payments.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_webhook_payments` | NOT_STARTED | NOT_TESTED | |

## Pre-filled Summary

- This draft is generated from filesystem paths and light code heuristics.
- Validate HTTP methods and auth rules per route before execution planning.
- Prefer assigning `P0` ownership first for: invoice, payment, renew, client status/update, auth.

## Sign-off Checklist

- [ ] API methods manually validated
- [ ] Auth and role mapping filled
- [ ] Owner assigned for all P0/P1 routes
- [ ] Laravel controller mapping refined to real class/method names
- [ ] First 20 P0 routes selected for sprint-1

```

## File: ROUTE_INVENTORY_TEMPLATE.md

```
# Route Inventory Template

Purpose:
- Legacy routes (page + API) সম্পূর্ণ তালিকা তৈরি করা
- Laravel migration priority ও ownership ঠিক করা
- Auth, dependency, risk, parity status track করা

How to use:
- প্রতিটি route/path এক লাইনে যুক্ত করুন
- Unknown data থাকলে `TBD` লিখুন
- Status প্রতি sprint-এ update করুন

## Legend

- Route Type: `PAGE`, `API`, `AJAX`, `WEBHOOK`, `CRON`
- Auth: `PUBLIC`, `LOGIN_REQUIRED`, `ROLE_BASED`
- Priority: `P0`, `P1`, `P2`
- Migration Status: `NOT_STARTED`, `IN_PROGRESS`, `DONE`, `DEFERRED`
- Parity Status: `NOT_TESTED`, `MATCHED`, `MISMATCH`

## Inventory Table

| ID | Legacy Path | Route Type | Method | Module | Auth | Role/Permission | Input Params | Output Type | Used By (UI/App/Cron) | Dependency (DB/API/Service) | Priority | Owner | Laravel Target Controller@Method | Feature Flag | Migration Status | Parity Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | /index.php | PAGE | GET | Dashboard | LOGIN_REQUIRED | admin.dashboard.view | N/A | HTML | UI | DB: summary queries | P0 | TBD | DashboardController@index | ff_dashboard_laravel | NOT_STARTED | NOT_TESTED | |
| 2 | /api/quick_search.php | API | GET | Client | LOGIN_REQUIRED | client.search | q, limit | JSON | UI/AJAX | DB: clients table | P0 | TBD | Api\ClientController@quickSearch | ff_client_api_laravel | NOT_STARTED | NOT_TESTED | |
| 3 | /api/invoice_create.php | API | POST | Invoice | ROLE_BASED | invoice.create | client_id, amount, due_date | JSON | UI/App | DB: invoices, billing calc | P0 | TBD | Api\InvoiceController@store | ff_invoice_api_laravel | NOT_STARTED | NOT_TESTED | |

## Module Summary

| Module | Total Routes | P0 | P1 | P2 | Done | In Progress | Not Started | Mismatch |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Auth/ACL | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Client | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Package | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Invoice/Billing | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Payment | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Router/OLT | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Reports | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |

## High-Risk Route Tracking (P0)

| Legacy Path | Risk Type | Failure Impact | Mitigation | Rollback Option | Owner | Status |
|---|---|---|---|---|---|---|
| /api/invoice_create.php | Financial correctness | Wrong billing/invoice amount | Golden dataset parity tests | Legacy route fallback | TBD | Open |
| /api/payment_mark_paid.php | Payment integrity | Duplicate or missing payment | Idempotency + reconciliation | Legacy route fallback | TBD | Open |
| /api/bkash_webhook.php | Webhook reliability | Lost payment callback | Retry + DLQ + audit logs | Temporary legacy webhook | TBD | Open |

## Route Sign-off Checklist

- [ ] সব legacy PAGE routes inventory-তে আছে
- [ ] সব API/AJAX routes inventory-তে আছে
- [ ] সব WEBHOOK/CRON routes inventory-তে আছে
- [ ] Auth + permission mapping সম্পন্ন
- [ ] P0 routes owner assigned
- [ ] Laravel target controller mapping complete
- [ ] First parity test pass evidence attached

```

## File: SPRINT1_P0_API_PARITY.md

```
# Sprint-1 P0 API Parity Tracker

Source:
- `docs/SPRINT1_P0_ROUTES.md`
- `docs/API_CONTRACT_PARITY_TEMPLATE.md`

Purpose:
- Sprint-1 এ P0 API endpointগুলোর contract parity centrally track করা
- Request/Response/Behavior mismatch early detect করা

## 1) P0 API Master Tracker

| Endpoint | Method | Module | Proposed Laravel Target | Owner | Request Match | Success Match | Error Match | Behavior Match | Overall | Go-Live Blocker |
|---|---|---|---|---|---|---|---|---|---|---|
| `/api/bkash_pgw_callback.php` | GET | Payment | `Api\PaymentController@bkashPgwCallback` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/bkash_webhook.php` | GET/POST | Payment | `Api\PaymentController@bkashWebhook` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_change_package.php` | POST | Client | `Api\ClientController@changePackage` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_expiry_update.php` | POST | Client | `Api\ClientController@updateExpiry` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_last_logout.php` | POST | Client | `Api\ClientController@lastLogout` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_left_bulk.php` | POST | Client | `Api\ClientController@leftBulk` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_left_toggle.php` | POST | Client | `Api\ClientController@leftToggle` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_live_status.php` | GET/POST | Client | `Api\ClientController@liveStatus` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_meta.php` | GET | Client | `Api\ClientController@meta` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_restore.php` | POST | Client | `Api\ClientController@restore` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/client_status.php` | GET | Client | `Api\ClientController@status` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/invoice_create.php` | POST | Invoice/Billing | `Api\InvoiceController@store` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/invoice_mark_paid.php` | POST | Invoice/Billing | `Api\InvoiceController@markPaid` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/invoice_quick_renew.php` | POST | Invoice/Billing | `Api\RenewController@quickRenew` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/payment_mark_paid.php` | POST | Payment | `Api\PaymentController@markPaid` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/quick_search.php` | GET | Client | `Api\ClientController@quickSearch` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/renew.php` | POST | Invoice/Billing | `Api\RenewController@renew` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/sms_bkash.php` | POST | Payment | `Api\BkashSmsController@store` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/suggest_clients.php` | GET | Client | `Api\ClientController@suggest` | TBD | N | N | N | N | MISMATCH | YES |
| `/api/update_expiry.php` | POST | Invoice/Billing | `Api\RenewController@updateExpiry` | TBD | N | N | N | N | MISMATCH | YES |

## 2) Endpoint Contract Sheets (Prefilled)

Status legend:
- `Not Started`: no contract validation evidence yet
- `In Progress`: legacy vs laravel sample collected
- `Matched`: all sections passed
- `Mismatch`: any blocking diff found

---

### 2.1 `/api/bkash_pgw_callback.php`

- Method: `GET`
- Module: `Payment`
- Proposed Laravel Path: `/api/bkash/pgw-callback`
- Proposed Controller: `Api\PaymentController@bkashPgwCallback`
- Priority: `P0`
- Status: `Not Started`

Request contract:
| Field | Type | Required | Legacy Rule | Laravel Rule | Match | Notes |
|---|---|---|---|---|---|---|
| TBD | TBD | TBD | TBD | TBD | N | Collect from legacy code |

Success response:
- Legacy status: TBD
- Laravel status: TBD

| Key Path | Type | Legacy Example | Laravel Example | Match | Notes |
|---|---|---|---|---|---|
| TBD | TBD | TBD | TBD | N | Capture sample payload |

Error contract:
| Scenario | Legacy Status | Laravel Status | Legacy Shape | Laravel Shape | Match | Notes |
|---|---:|---:|---|---|---|---|
| Validation failed | TBD | TBD | TBD | TBD | N | |
| Unauthorized | TBD | TBD | TBD | TBD | N | |
| Server error | TBD | TBD | TBD | TBD | N | |

Behavior parity:
| Behavior | Legacy | Laravel | Match | Evidence |
|---|---|---|---|---|
| Idempotent callback handling | TBD | TBD | N | |
| Duplicate callback behavior | TBD | TBD | N | |
| Payment reconciliation side effect | TBD | TBD | N | |

---

### 2.2 `/api/bkash_webhook.php`

- Method: `GET/POST`
- Module: `Payment`
- Proposed Laravel Path: `/api/bkash/webhook`
- Proposed Controller: `Api\PaymentController@bkashWebhook`
- Priority: `P0`
- Status: `Not Started`

Request contract:
| Field | Type | Required | Legacy Rule | Laravel Rule | Match | Notes |
|---|---|---|---|---|---|---|
| TBD | TBD | TBD | TBD | TBD | N | Include signature headers |

Success response:
- Legacy status: TBD
- Laravel status: TBD

| Key Path | Type | Legacy Example | Laravel Example | Match | Notes |
|---|---|---|---|---|---|
| TBD | TBD | TBD | TBD | N | |

Error contract:
| Scenario | Legacy Status | Laravel Status | Legacy Shape | Laravel Shape | Match | Notes |
|---|---:|---:|---|---|---|---|
| Validation failed | TBD | TBD | TBD | TBD | N | |
| Unauthorized | TBD | TBD | TBD | TBD | N | |
| Server error | TBD | TBD | TBD | TBD | N | |

Behavior parity:
| Behavior | Legacy | Laravel | Match | Evidence |
|---|---|---|---|---|
| Idempotency key handling | TBD | TBD | N | |
| Retries and duplicate events | TBD | TBD | N | |
| Financial side effects | TBD | TBD | N | |

---

### 2.3 `/api/client_change_package.php`

- Method: `POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/change-package`
- Proposed Controller: `Api\ClientController@changePackage`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Request fields and validation parity
- Package/profile update side effects parity
- Error message shape parity

---

### 2.4 `/api/client_expiry_update.php`

- Method: `POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/expiry-update`
- Proposed Controller: `Api\ClientController@updateExpiry`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Expiry date calculation parity
- Timezone handling parity
- Audit trail insertion parity

---

### 2.5 `/api/client_last_logout.php`

- Method: `POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/last-logout`
- Proposed Controller: `Api\ClientController@lastLogout`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Response key parity
- Last logout source parity (router/cache/db)

---

### 2.6 `/api/client_left_bulk.php`

- Method: `POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/left-bulk`
- Proposed Controller: `Api\ClientController@leftBulk`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Bulk payload validation parity
- Partial failure behavior parity

---

### 2.7 `/api/client_left_toggle.php`

- Method: `POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/left-toggle`
- Proposed Controller: `Api\ClientController@leftToggle`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Toggle idempotency parity
- UI status reflection parity

---

### 2.8 `/api/client_live_status.php`

- Method: `GET/POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/live-status`
- Proposed Controller: `Api\ClientController@liveStatus`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Online/offline detection parity
- Cache staleness behavior parity

---

### 2.9 `/api/client_meta.php`

- Method: `GET`
- Module: `Client`
- Proposed Laravel Path: `/api/client/meta`
- Proposed Controller: `Api\ClientController@meta`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Returned fields parity
- Null/default value behavior parity

---

### 2.10 `/api/client_restore.php`

- Method: `POST`
- Module: `Client`
- Proposed Laravel Path: `/api/client/restore`
- Proposed Controller: `Api\ClientController@restore`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Restore side effects parity
- Permission enforcement parity

---

### 2.11 `/api/client_status.php`

- Method: `GET`
- Module: `Client`
- Proposed Laravel Path: `/api/client/status`
- Proposed Controller: `Api\ClientController@status`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Status enum mapping parity
- HTTP status code parity

---

### 2.12 `/api/invoice_create.php`

- Method: `POST`
- Module: `Invoice/Billing`
- Proposed Laravel Path: `/api/invoice/create`
- Proposed Controller: `Api\InvoiceController@store`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Billing calculation parity
- Duplicate invoice guard parity
- Financial rounding parity

---

### 2.13 `/api/invoice_mark_paid.php`

- Method: `POST`
- Module: `Invoice/Billing`
- Proposed Laravel Path: `/api/invoice/mark-paid`
- Proposed Controller: `Api\InvoiceController@markPaid`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Transaction safety parity
- Ledger update parity
- Duplicate payment protection parity

---

### 2.14 `/api/invoice_quick_renew.php`

- Method: `POST`
- Module: `Invoice/Billing`
- Proposed Laravel Path: `/api/invoice/quick-renew`
- Proposed Controller: `Api\RenewController@quickRenew`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Renew duration parity
- Invoice+renew coupling parity

---

### 2.15 `/api/payment_mark_paid.php`

- Method: `POST`
- Module: `Payment`
- Proposed Laravel Path: `/api/payment/mark-paid`
- Proposed Controller: `Api\PaymentController@markPaid`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Idempotent payment marking
- Reconciliation parity
- Audit log parity

---

### 2.16 `/api/quick_search.php`

- Method: `GET`
- Module: `Client`
- Proposed Laravel Path: `/api/client/quick-search`
- Proposed Controller: `Api\ClientController@quickSearch`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Search result order parity
- Limit/pagination behavior parity

---

### 2.17 `/api/renew.php`

- Method: `POST`
- Module: `Invoice/Billing`
- Proposed Laravel Path: `/api/renew`
- Proposed Controller: `Api\RenewController@renew`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Renewal logic parity
- Expiry date roll-forward parity

---

### 2.18 `/api/sms_bkash.php`

- Method: `POST`
- Module: `Payment`
- Proposed Laravel Path: `/api/bkash/sms`
- Proposed Controller: `Api\BkashSmsController@store`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- SMS parser parity
- Transaction match behavior parity

---

### 2.19 `/api/suggest_clients.php`

- Method: `GET`
- Module: `Client`
- Proposed Laravel Path: `/api/client/suggest`
- Proposed Controller: `Api\ClientController@suggest`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Suggestion relevance/order parity
- Empty query behavior parity

---

### 2.20 `/api/update_expiry.php`

- Method: `POST`
- Module: `Invoice/Billing`
- Proposed Laravel Path: `/api/renew/update-expiry`
- Proposed Controller: `Api\RenewController@updateExpiry`
- Priority: `P0`
- Status: `Not Started`

Key checks:
- Manual expiry update validation parity
- Historical data consistency parity

## 3) Sprint Gate Criteria

- [ ] 20/20 P0 API request contract documented
- [ ] 20/20 P0 API success response samples captured
- [ ] 20/20 P0 API error behavior compared
- [ ] Payment endpoints idempotency tests passed
- [ ] Invoice/renew financial parity tests passed
- [ ] No `MISMATCH` left for go-live P0 scope

## 4) Review Sign-off

- QA Lead:
- Backend Lead:
- Billing/Finance Reviewer:
- Approval Date:

```

## File: SPRINT1_P0_API_ROUTES_PRODUCTION_SAFE.md

```
# Sprint-1 P0 API Routes (Production-Safe)

Purpose:
- P0 routes কে production-safe layout-এ সাজানো
- Public webhooks এবং authenticated business APIs আলাদা রাখা
- Legacy `*.php` paths preserve করে gradual migration enable করা

## Design Rules

- Rule 1: External webhook endpoints (`bKash` callback/webhook) `auth:sanctum` ছাড়া expose হবে
- Rule 2: Webhook security `signature/ip allowlist + rate limit + idempotency`
- Rule 3: Business APIs `auth:sanctum + permission` middleware এর ভিতরে চলবে
- Rule 4: Legacy path and canonical `v1` path parallel রাখা হবে migration window জুড়ে
- Rule 5: Financial routes transaction-safe এবং audit-logged হতে হবে

## `routes/api.php` Example

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\RenewController;
use App\Http\Controllers\Api\BkashSmsController;

/*
|--------------------------------------------------------------------------
| 1) Public Webhooks (No auth:sanctum)
|--------------------------------------------------------------------------
| Keep strict verification middleware: signature + throttle + replay guard.
*/
Route::middleware([
    'throttle:webhooks',
    'verify.bkash.signature',
    'reject.replay',
])->group(function () {
    Route::get('bkash_pgw_callback.php', [PaymentController::class, 'bkashPgwCallback'])
        ->name('legacy.bkash_pgw_callback');

    Route::match(['GET', 'POST'], 'bkash_webhook.php', [PaymentController::class, 'bkashWebhook'])
        ->name('legacy.bkash_webhook');

    Route::post('sms_bkash.php', [BkashSmsController::class, 'store'])
        ->name('legacy.sms_bkash');

    // Canonical paths for new integrations
    Route::prefix('v1/payments/bkash')->group(function () {
        Route::get('pgw-callback', [PaymentController::class, 'bkashPgwCallback'])
            ->name('v1.bkash_pgw_callback');
        Route::match(['GET', 'POST'], 'webhook', [PaymentController::class, 'bkashWebhook'])
            ->name('v1.bkash_webhook');
        Route::post('sms', [BkashSmsController::class, 'store'])
            ->name('v1.sms_bkash');
    });
});

/*
|--------------------------------------------------------------------------
| 2) Authenticated P0 APIs
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Client P0
    Route::post('client_change_package.php', [ClientController::class, 'changePackage'])
        ->middleware('permission:client.change_package')
        ->name('legacy.client_change_package');

    Route::post('client_expiry_update.php', [ClientController::class, 'updateExpiry'])
        ->middleware('permission:client.update_expiry')
        ->name('legacy.client_expiry_update');

    Route::post('client_last_logout.php', [ClientController::class, 'lastLogout'])
        ->middleware('permission:client.view_last_logout')
        ->name('legacy.client_last_logout');

    Route::post('client_left_bulk.php', [ClientController::class, 'leftBulk'])
        ->middleware('permission:client.left_bulk')
        ->name('legacy.client_left_bulk');

    Route::post('client_left_toggle.php', [ClientController::class, 'leftToggle'])
        ->middleware('permission:client.left_toggle')
        ->name('legacy.client_left_toggle');

    Route::match(['GET', 'POST'], 'client_live_status.php', [ClientController::class, 'liveStatus'])
        ->middleware('permission:client.view_live_status')
        ->name('legacy.client_live_status');

    Route::get('client_meta.php', [ClientController::class, 'meta'])
        ->middleware('permission:client.view_meta')
        ->name('legacy.client_meta');

    Route::post('client_restore.php', [ClientController::class, 'restore'])
        ->middleware('permission:client.restore')
        ->name('legacy.client_restore');

    Route::get('client_status.php', [ClientController::class, 'status'])
        ->middleware('permission:client.view_status')
        ->name('legacy.client_status');

    Route::get('quick_search.php', [ClientController::class, 'quickSearch'])
        ->middleware('permission:client.quick_search')
        ->name('legacy.quick_search');

    Route::get('suggest_clients.php', [ClientController::class, 'suggest'])
        ->middleware('permission:client.suggest')
        ->name('legacy.suggest_clients');

    // Invoice / Renew P0
    Route::post('invoice_create.php', [InvoiceController::class, 'store'])
        ->middleware('permission:invoice.create')
        ->name('legacy.invoice_create');

    Route::post('invoice_mark_paid.php', [InvoiceController::class, 'markPaid'])
        ->middleware('permission:invoice.mark_paid')
        ->name('legacy.invoice_mark_paid');

    Route::post('invoice_quick_renew.php', [RenewController::class, 'quickRenew'])
        ->middleware('permission:invoice.quick_renew')
        ->name('legacy.invoice_quick_renew');

    Route::post('renew.php', [RenewController::class, 'renew'])
        ->middleware('permission:billing.renew')
        ->name('legacy.renew');

    Route::post('update_expiry.php', [RenewController::class, 'updateExpiry'])
        ->middleware('permission:billing.update_expiry')
        ->name('legacy.update_expiry');

    // Payment P0 (internal/manual)
    Route::post('payment_mark_paid.php', [PaymentController::class, 'markPaid'])
        ->middleware(['permission:payment.mark_paid', 'idempotency'])
        ->name('legacy.payment_mark_paid');

    // Canonical v1 aliases
    Route::prefix('v1')->group(function () {
        Route::prefix('clients')->group(function () {
            Route::post('change-package', [ClientController::class, 'changePackage']);
            Route::post('update-expiry', [ClientController::class, 'updateExpiry']);
            Route::post('last-logout', [ClientController::class, 'lastLogout']);
            Route::post('left-bulk', [ClientController::class, 'leftBulk']);
            Route::post('left-toggle', [ClientController::class, 'leftToggle']);
            Route::match(['GET', 'POST'], 'live-status', [ClientController::class, 'liveStatus']);
            Route::get('meta', [ClientController::class, 'meta']);
            Route::post('restore', [ClientController::class, 'restore']);
            Route::get('status', [ClientController::class, 'status']);
            Route::get('quick-search', [ClientController::class, 'quickSearch']);
            Route::get('suggest', [ClientController::class, 'suggest']);
        });

        Route::prefix('invoices')->group(function () {
            Route::post('create', [InvoiceController::class, 'store']);
            Route::post('mark-paid', [InvoiceController::class, 'markPaid']);
            Route::post('quick-renew', [RenewController::class, 'quickRenew']);
        });

        Route::prefix('renew')->group(function () {
            Route::post('/', [RenewController::class, 'renew']);
            Route::post('update-expiry', [RenewController::class, 'updateExpiry']);
        });

        Route::prefix('payments')->group(function () {
            Route::post('mark-paid', [PaymentController::class, 'markPaid'])
                ->middleware('idempotency');
        });
    });
});
```

## Required Middleware Checklist

- [ ] `verify.bkash.signature` middleware implemented
- [ ] `reject.replay` middleware implemented (nonce/timestamp)
- [ ] `idempotency` middleware implemented for payment write endpoints
- [ ] `permission:*` permissions registered and seeded
- [ ] `throttle:webhooks` and `throttle:api` rates configured

## Quick Security Defaults

- Webhook throttle: `60/min` per source IP + signature key
- API throttle: `120/min` per authenticated user token
- Audit log: invoice/payment/client status write actions
- Sensitive logs: no raw token/signature dump in production logs

## Cutover Notes

- Step 1: only legacy paths enable করুন
- Step 2: parity tests pass হলে `v1` canonical docs publish করুন
- Step 3: clients migrate হলে legacy path deprecation header দিন
- Step 4: final window-এ legacy aliases remove করুন

```

## File: SPRINT1_P0_LARAVEL_ROUTE_ALIASES.md

```
# Sprint-1 P0 Laravel Route Aliases

Purpose:
- Legacy API path unchanged রেখে Laravel controller-এ route map করা
- একই endpoint-এর জন্য canonical Laravel path expose করা

Usage:
- এই ব্লক `routes/api.php`-এ কপি করুন
- `auth`/`permission` middleware আপনার policy অনুযায়ী adjust করুন
- Controller method names project convention অনুযায়ী rename করতে পারবেন

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\RenewController;
use App\Http\Controllers\Api\BkashSmsController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Payment P0
    Route::match(['GET'], 'bkash_pgw_callback.php', [PaymentController::class, 'bkashPgwCallback']);
    Route::match(['GET', 'POST'], 'bkash_webhook.php', [PaymentController::class, 'bkashWebhook']);
    Route::post('payment_mark_paid.php', [PaymentController::class, 'markPaid']);
    Route::post('sms_bkash.php', [BkashSmsController::class, 'store']);

    // Client P0
    Route::post('client_change_package.php', [ClientController::class, 'changePackage']);
    Route::post('client_expiry_update.php', [ClientController::class, 'updateExpiry']);
    Route::post('client_last_logout.php', [ClientController::class, 'lastLogout']);
    Route::post('client_left_bulk.php', [ClientController::class, 'leftBulk']);
    Route::post('client_left_toggle.php', [ClientController::class, 'leftToggle']);
    Route::match(['GET', 'POST'], 'client_live_status.php', [ClientController::class, 'liveStatus']);
    Route::get('client_meta.php', [ClientController::class, 'meta']);
    Route::post('client_restore.php', [ClientController::class, 'restore']);
    Route::get('client_status.php', [ClientController::class, 'status']);
    Route::get('quick_search.php', [ClientController::class, 'quickSearch']);
    Route::get('suggest_clients.php', [ClientController::class, 'suggest']);

    // Invoice and Renew P0
    Route::post('invoice_create.php', [InvoiceController::class, 'store']);
    Route::post('invoice_mark_paid.php', [InvoiceController::class, 'markPaid']);
    Route::post('invoice_quick_renew.php', [RenewController::class, 'quickRenew']);
    Route::post('renew.php', [RenewController::class, 'renew']);
    Route::post('update_expiry.php', [RenewController::class, 'updateExpiry']);
});

// Optional canonical paths (keep both during migration window)
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Payment
    Route::get('payments/bkash/pgw-callback', [PaymentController::class, 'bkashPgwCallback']);
    Route::match(['GET', 'POST'], 'payments/bkash/webhook', [PaymentController::class, 'bkashWebhook']);
    Route::post('payments/mark-paid', [PaymentController::class, 'markPaid']);
    Route::post('payments/bkash/sms', [BkashSmsController::class, 'store']);

    // Clients
    Route::post('clients/change-package', [ClientController::class, 'changePackage']);
    Route::post('clients/update-expiry', [ClientController::class, 'updateExpiry']);
    Route::post('clients/last-logout', [ClientController::class, 'lastLogout']);
    Route::post('clients/left-bulk', [ClientController::class, 'leftBulk']);
    Route::post('clients/left-toggle', [ClientController::class, 'leftToggle']);
    Route::match(['GET', 'POST'], 'clients/live-status', [ClientController::class, 'liveStatus']);
    Route::get('clients/meta', [ClientController::class, 'meta']);
    Route::post('clients/restore', [ClientController::class, 'restore']);
    Route::get('clients/status', [ClientController::class, 'status']);
    Route::get('clients/quick-search', [ClientController::class, 'quickSearch']);
    Route::get('clients/suggest', [ClientController::class, 'suggest']);

    // Invoices and renew
    Route::post('invoices/create', [InvoiceController::class, 'store']);
    Route::post('invoices/mark-paid', [InvoiceController::class, 'markPaid']);
    Route::post('renew/quick', [RenewController::class, 'quickRenew']);
    Route::post('renew', [RenewController::class, 'renew']);
    Route::post('renew/update-expiry', [RenewController::class, 'updateExpiry']);
});
```

## Validation Checklist

- [ ] Legacy path response JSON keys unchanged
- [ ] Legacy status codes unchanged
- [ ] Payment endpoints idempotency tested
- [ ] Invoice and renew financial parity tested
- [ ] Route middleware mapping reviewed by security owner

## Notes

- First cut-এ legacy `*.php` API paths keep করা safest approach.
- Canonical `v1` paths gradual adoption-এর জন্য, immediate switch নয়.

```

## File: SPRINT1_P0_ROUTES.md

```
]633;E;{   echo '# Sprint-1 P0 Routes'\x3b   echo ''\x3b   echo 'Source: `docs/ROUTE_INVENTORY_PREFILLED.md`'\x3b   echo 'Selection rule: `Priority = P0`'\x3b   echo ''\x3b   echo '## P0 Route Table'\x3b   echo ''\x3b   awk 'BEGIN{printed=0} /^\\| ID \\|/{print\x3b getline\x3b print\x3b printed=1\x3b next} printed && /^\\| [0-9]+ \\|/ { if ($0 ~ /\\| P0 \\|/) print }' docs/ROUTE_INVENTORY_PREFILLED.md\x3b   echo ''\x3b   echo '## Sprint-1 Suggested Execution Order'\x3b   echo ''\x3b   echo '1. Auth routes (`/public/login.php`, `/public/logout.php`, `/public/verify.php`)'\x3b   echo '2. Client core APIs (`/api/quick_search.php`, `/api/suggest_clients.php`, `/api/client_*`)'\x3b   echo '3. Invoice/Billing APIs (`/api/invoice_create.php`, `/api/invoice_mark_paid.php`, `/api/renew.php`, `/api/update_expiry.php`)'\x3b   echo '4. Payment critical routes (`/api/payment_mark_paid.php`, `/api/bkash_webhook.php`, `/api/bkash_pgw_callback.php`)'\x3b   echo '5. Payment/admin pages (`/public/payment*.php`, `/public/payments_*.php`, `/public/bkash_*.php`)'\x3b   echo ''\x3b   echo '## Assignment Checklist'\x3b   echo ''\x3b   echo '- [ ] Owner assigned for each P0 route'\x3b   echo '- [ ] Auth/role mapping filled for each P0 route'\x3b   echo '- [ ] API contract parity sheet created for each P0 API'\x3b   echo '- [ ] Unit/integration tests mapped for each P0 module'\x3b   echo '- [ ] Canary release plan ready for payment and invoice routes'\x3b } > docs/SPRINT1_P0_ROUTES.md;fb83547b-5e7b-4e91-bdb7-32c0ce4cdb6b]633;C# Sprint-1 P0 Routes

Source: `docs/ROUTE_INVENTORY_PREFILLED.md`
Selection rule: `Priority = P0`

## P0 Route Table

| ID | Legacy Path | Route Type | Method | Module | Auth | Role/Permission | Input Params | Output Type | Used By (UI/App/Cron) | Dependency (DB/API/Service) | Priority | Owner | Laravel Target Controller@Method | Feature Flag | Migration Status | Parity Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 3 | `/api/bkash_pgw_callback.php` | WEBHOOK | GET | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@bkash_pgw_callback` | `ff_api_bkash_pgw_callback` | NOT_STARTED | NOT_TESTED | |
| 4 | `/api/bkash_webhook.php` | WEBHOOK | GET/POST | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@bkash_webhook` | `ff_api_bkash_webhook` | NOT_STARTED | NOT_TESTED | |
| 9 | `/api/client_change_package.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_change_package` | `ff_api_client_change_package` | NOT_STARTED | NOT_TESTED | |
| 10 | `/api/client_expiry_update.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_expiry_update` | `ff_api_client_expiry_update` | NOT_STARTED | NOT_TESTED | |
| 11 | `/api/client_last_logout.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_last_logout` | `ff_api_client_last_logout` | NOT_STARTED | NOT_TESTED | |
| 12 | `/api/client_left_bulk.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_left_bulk` | `ff_api_client_left_bulk` | NOT_STARTED | NOT_TESTED | |
| 13 | `/api/client_left_toggle.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_left_toggle` | `ff_api_client_left_toggle` | NOT_STARTED | NOT_TESTED | |
| 14 | `/api/client_live_status.php` | API | GET/POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_live_status` | `ff_api_client_live_status` | NOT_STARTED | NOT_TESTED | |
| 15 | `/api/client_meta.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_meta` | `ff_api_client_meta` | NOT_STARTED | NOT_TESTED | |
| 16 | `/api/client_restore.php` | API | POST | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_restore` | `ff_api_client_restore` | NOT_STARTED | NOT_TESTED | |
| 17 | `/api/client_status.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@client_status` | `ff_api_client_status` | NOT_STARTED | NOT_TESTED | |
| 20 | `/api/invoice_create.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@invoice_create` | `ff_api_invoice_create` | NOT_STARTED | NOT_TESTED | |
| 21 | `/api/invoice_mark_paid.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@invoice_mark_paid` | `ff_api_invoice_mark_paid` | NOT_STARTED | NOT_TESTED | |
| 22 | `/api/invoice_quick_renew.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@invoice_quick_renew` | `ff_api_invoice_quick_renew` | NOT_STARTED | NOT_TESTED | |
| 38 | `/api/payment_mark_paid.php` | WEBHOOK | POST | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@payment_mark_paid` | `ff_api_payment_mark_paid` | NOT_STARTED | NOT_TESTED | |
| 44 | `/api/quick_search.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@quick_search` | `ff_api_quick_search` | NOT_STARTED | NOT_TESTED | |
| 45 | `/api/renew.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@renew` | `ff_api_renew` | NOT_STARTED | NOT_TESTED | |
| 50 | `/api/sms_bkash.php` | WEBHOOK | POST | Payment | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\PaymentController@sms_bkash` | `ff_api_sms_bkash` | NOT_STARTED | NOT_TESTED | |
| 52 | `/api/suggest_clients.php` | API | GET | Client | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\ClientController@suggest_clients` | `ff_api_suggest_clients` | NOT_STARTED | NOT_TESTED | |
| 54 | `/api/update_expiry.php` | API | POST | Invoice/Billing | TBD | TBD | TBD | JSON | UI/App | DB/Service | P0 | TBD | `Api\InvoiceBillingController@update_expiry` | `ff_api_update_expiry` | NOT_STARTED | NOT_TESTED | |
| 13 | `/public/bkash_inbox.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_bkash_inbox` | NOT_STARTED | NOT_TESTED | |
| 14 | `/public/bkash_rtn_dashboard.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_bkash_rtn_dashboard` | NOT_STARTED | NOT_TESTED | |
| 15 | `/public/bkash_sms_tester.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_bkash_sms_tester` | NOT_STARTED | NOT_TESTED | |
| 16 | `/public/client_add.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_add` | NOT_STARTED | NOT_TESTED | |
| 17 | `/public/client_edit.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_edit` | NOT_STARTED | NOT_TESTED | |
| 18 | `/public/client_geo_bulk.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_geo_bulk` | NOT_STARTED | NOT_TESTED | |
| 19 | `/public/client_geo_picker.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_geo_picker` | NOT_STARTED | NOT_TESTED | |
| 20 | `/public/client_geo_save.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_geo_save` | NOT_STARTED | NOT_TESTED | |
| 21 | `/public/client_invoices.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_invoices` | NOT_STARTED | NOT_TESTED | |
| 22 | `/public/client_ledger_balance_update.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_ledger_balance_update` | NOT_STARTED | NOT_TESTED | |
| 23 | `/public/client_ledger - Copy.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_ledger - Copy` | NOT_STARTED | NOT_TESTED | |
| 24 | `/public/client_ledger.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_ledger` | NOT_STARTED | NOT_TESTED | |
| 25 | `/public/client_list_by_status.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_list_by_status` | NOT_STARTED | NOT_TESTED | |
| 26 | `/public/client_live_graph.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_live_graph` | NOT_STARTED | NOT_TESTED | |
| 27 | `/public/client_payment_add_query.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_payment_add_query` | NOT_STARTED | NOT_TESTED | |
| 28 | `/public/client_payment_delete.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_payment_delete` | NOT_STARTED | NOT_TESTED | |
| 29 | `/public/client_payments.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_payments` | NOT_STARTED | NOT_TESTED | |
| 30 | `/public/clients_offline.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients_offline` | NOT_STARTED | NOT_TESTED | |
| 31 | `/public/clients_online.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients_online` | NOT_STARTED | NOT_TESTED | |
| 32 | `/public/clients.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients` | NOT_STARTED | NOT_TESTED | |
| 33 | `/public/clients_search.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_clients_search` | NOT_STARTED | NOT_TESTED | |
| 34 | `/public/client_status.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_status` | NOT_STARTED | NOT_TESTED | |
| 35 | `/public/client_view.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_view` | NOT_STARTED | NOT_TESTED | |
| 36 | `/public/client_whitelist.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_client_whitelist` | NOT_STARTED | NOT_TESTED | |
| 37 | `/public/collections.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_collections` | NOT_STARTED | NOT_TESTED | |
| 39 | `/public/deleted_clients.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_deleted_clients` | NOT_STARTED | NOT_TESTED | |
| 41 | `/public/due_analytics.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_due_analytics` | NOT_STARTED | NOT_TESTED | |
| 42 | `/public/due_report.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_due_report` | NOT_STARTED | NOT_TESTED | |
| 43 | `/public/due_report_pro.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_due_report_pro` | NOT_STARTED | NOT_TESTED | |
| 53 | `/public/forgot.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_forgot` | NOT_STARTED | NOT_TESTED | |
| 61 | `/public/invoice_amount_update.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_amount_update` | NOT_STARTED | NOT_TESTED | |
| 62 | `/public/invoice_delete.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_delete` | NOT_STARTED | NOT_TESTED | |
| 63 | `/public/invoice_generate.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_generate` | NOT_STARTED | NOT_TESTED | |
| 64 | `/public/invoice_list.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_list` | NOT_STARTED | NOT_TESTED | |
| 65 | `/public/invoice_new.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_new` | NOT_STARTED | NOT_TESTED | |
| 66 | `/public/invoice_pdf.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_pdf` | NOT_STARTED | NOT_TESTED | |
| 67 | `/public/invoice_pdf_template.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_pdf_template` | NOT_STARTED | NOT_TESTED | |
| 68 | `/public/invoice_print.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_print` | NOT_STARTED | NOT_TESTED | |
| 69 | `/public/invoices.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoices` | NOT_STARTED | NOT_TESTED | |
| 70 | `/public/invoice_view.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_invoice_view` | NOT_STARTED | NOT_TESTED | |
| 72 | `/public/login.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_login` | NOT_STARTED | NOT_TESTED | |
| 73 | `/public/logout.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_logout` | NOT_STARTED | NOT_TESTED | |
| 84 | `/public/payment_add2.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_add2` | NOT_STARTED | NOT_TESTED | |
| 85 | `/public/payment_add.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_add` | NOT_STARTED | NOT_TESTED | |
| 86 | `/public/payment_bkash.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_bkash` | NOT_STARTED | NOT_TESTED | |
| 87 | `/public/payment_delete.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_delete` | NOT_STARTED | NOT_TESTED | |
| 88 | `/public/payment_edit.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_edit` | NOT_STARTED | NOT_TESTED | |
| 89 | `/public/payment_receipt.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_receipt` | NOT_STARTED | NOT_TESTED | |
| 90 | `/public/payment_report_export.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_report_export` | NOT_STARTED | NOT_TESTED | |
| 91 | `/public/payment_report.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payment_report` | NOT_STARTED | NOT_TESTED | |
| 92 | `/public/payments_add_query.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payments_add_query` | NOT_STARTED | NOT_TESTED | |
| 93 | `/public/payments_bkash_inbox.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payments_bkash_inbox` | NOT_STARTED | NOT_TESTED | |
| 94 | `/public/payments_bkash_match.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_payments_bkash_match` | NOT_STARTED | NOT_TESTED | |
| 99 | `/public/receipt_payment.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_receipt_payment` | NOT_STARTED | NOT_TESTED | |
| 100 | `/public/register.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_register` | NOT_STARTED | NOT_TESTED | |
| 112 | `/public/reset.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_reset` | NOT_STARTED | NOT_TESTED | |
| 129 | `/public/suspended_clients.php` | PAGE | GET | Client | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\ClientController@index` | `ff_page_suspended_clients` | NOT_STARTED | NOT_TESTED | |
| 140 | `/public/verify.php` | PAGE | GET | Auth | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\AuthController@index` | `ff_page_verify` | NOT_STARTED | NOT_TESTED | |
| 141 | `/public/waiver_add.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_waiver_add` | NOT_STARTED | NOT_TESTED | |
| 142 | `/public/waivers.php` | PAGE | GET | Invoice/Billing | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\InvoiceBillingController@index` | `ff_page_waivers` | NOT_STARTED | NOT_TESTED | |
| 143 | `/public/wallet_accounts.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_accounts` | NOT_STARTED | NOT_TESTED | |
| 144 | `/public/wallet_approvals.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_approvals` | NOT_STARTED | NOT_TESTED | |
| 145 | `/public/wallets_dashboard2.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallets_dashboard2` | NOT_STARTED | NOT_TESTED | |
| 146 | `/public/wallets_dashboard.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallets_dashboard` | NOT_STARTED | NOT_TESTED | |
| 147 | `/public/wallet_seed.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_seed` | NOT_STARTED | NOT_TESTED | |
| 148 | `/public/wallet_settlement.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_settlement` | NOT_STARTED | NOT_TESTED | |
| 149 | `/public/wallets.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallets` | NOT_STARTED | NOT_TESTED | |
| 150 | `/public/wallet_statement.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_statement` | NOT_STARTED | NOT_TESTED | |
| 151 | `/public/wallet_transfer_action.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_wallet_transfer_action` | NOT_STARTED | NOT_TESTED | |
| 153 | `/public/webhook_payments.php` | PAGE | GET | Payment | TBD | TBD | TBD | HTML | UI | DB/Session | P0 | TBD | `Web\PaymentController@index` | `ff_page_webhook_payments` | NOT_STARTED | NOT_TESTED | |

## Sprint-1 Suggested Execution Order

1. Auth routes (`/public/login.php`, `/public/logout.php`, `/public/verify.php`)
2. Client core APIs (`/api/quick_search.php`, `/api/suggest_clients.php`, `/api/client_*`)
3. Invoice/Billing APIs (`/api/invoice_create.php`, `/api/invoice_mark_paid.php`, `/api/renew.php`, `/api/update_expiry.php`)
4. Payment critical routes (`/api/payment_mark_paid.php`, `/api/bkash_webhook.php`, `/api/bkash_pgw_callback.php`)
5. Payment/admin pages (`/public/payment*.php`, `/public/payments_*.php`, `/public/bkash_*.php`)

## Assignment Checklist

- [ ] Owner assigned for each P0 route
- [ ] Auth/role mapping filled for each P0 route
- [ ] API contract parity sheet created for each P0 API
- [ ] Unit/integration tests mapped for each P0 module
- [ ] Canary release plan ready for payment and invoice routes

```

