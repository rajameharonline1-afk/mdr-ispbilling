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
