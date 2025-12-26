#!/bin/bash
# /tools/webhook_test_bkash_rtn.sh
# Quick test script for bKash RTN webhook endpoint
# Usage: bash webhook_test_bkash_rtn.sh [TOKEN] [DOMAIN]

TOKEN="${1:-test-token-12345}"
DOMAIN="${2:-localhost}"
PROTOCOL="${3:-https}"

echo "=================================="
echo "bKash RTN Webhook Test"
echo "=================================="
echo "Domain: $DOMAIN"
echo "Protocol: $PROTOCOL"
echo "Token: $TOKEN"
echo ""

# Test 1: Valid webhook
echo "Test 1: Valid RTN Webhook (payment_success)"
echo "---"
curl -X POST "${PROTOCOL}://${DOMAIN}/api/bkash_rtn/notify.php" \
  -H "Content-Type: application/json" \
  -H "X-Bkash-Webhook-Token: ${TOKEN}" \
  -d '{
    "eventType": "payment_success",
    "eventId": "evt_20251214_001",
    "trxID": "TRX20251214000001",
    "transactionStatus": "COMPLETED",
    "amount": 1000.00,
    "customerMsisdn": "01712345678",
    "merchantInvoiceNumber": "INV-2025-001",
    "paymentID": "pay_20251214_001"
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 2: Missing token
echo "Test 2: Missing Token (should fail with 401)"
echo "---"
curl -X POST "${PROTOCOL}://${DOMAIN}/api/bkash_rtn/notify.php" \
  -H "Content-Type: application/json" \
  -d '{
    "eventType": "payment_success",
    "trxID": "TRX_TEST_002",
    "amount": 500
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 3: Invalid token
echo "Test 3: Invalid Token (should fail with 401)"
echo "---"
curl -X POST "${PROTOCOL}://${DOMAIN}/api/bkash_rtn/notify.php" \
  -H "Content-Type: application/json" \
  -H "X-Bkash-Webhook-Token: wrong-token" \
  -d '{
    "eventType": "payment_success",
    "trxID": "TRX_TEST_003",
    "amount": 500
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 4: Invalid JSON
echo "Test 4: Invalid JSON (should fail with 422)"
echo "---"
curl -X POST "${PROTOCOL}://${DOMAIN}/api/bkash_rtn/notify.php" \
  -H "Content-Type: application/json" \
  -H "X-Bkash-Webhook-Token: ${TOKEN}" \
  -d 'not-json' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 5: Query parameter token (alternative)
echo "Test 5: Token via Query Parameter (alternative method)"
echo "---"
curl -X POST "${PROTOCOL}://${DOMAIN}/api/bkash_rtn/notify.php?token=${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "eventType": "payment_success",
    "trxID": "TRX_TEST_004",
    "amount": 750.50,
    "customerMsisdn": "01987654321"
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 6: GET request (should fail with 405)
echo "Test 6: GET Request (should fail with 405, POST only)"
echo "---"
curl -i -X GET "${PROTOCOL}://${DOMAIN}/api/bkash_rtn/notify.php?token=${TOKEN}" \
  -w "\nHTTP Status: %{http_code}\n\n"

echo "=================================="
echo "Tests Complete"
echo "=================================="
echo "Check /var/www/isp_billing/storage/logs/bkash_auto_apply.log for cron processing"
echo "Check database: SELECT * FROM bkash_rtn_events ORDER BY id DESC LIMIT 5;"
