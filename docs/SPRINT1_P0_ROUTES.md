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
