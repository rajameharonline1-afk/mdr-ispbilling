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
