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
