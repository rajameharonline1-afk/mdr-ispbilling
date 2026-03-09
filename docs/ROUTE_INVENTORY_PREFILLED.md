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
