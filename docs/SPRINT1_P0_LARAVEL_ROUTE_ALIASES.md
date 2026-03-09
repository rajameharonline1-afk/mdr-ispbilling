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
