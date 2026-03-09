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
