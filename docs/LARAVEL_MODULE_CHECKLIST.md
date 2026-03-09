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
