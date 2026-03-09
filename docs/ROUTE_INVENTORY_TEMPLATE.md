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
