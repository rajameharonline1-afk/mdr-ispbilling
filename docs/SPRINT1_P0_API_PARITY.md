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
