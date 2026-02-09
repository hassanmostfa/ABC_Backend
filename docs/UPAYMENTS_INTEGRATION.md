# Upayments Gateway Integration Guide

This document describes how the **Upayments** payment gateway is integrated into this Laravel application for **order payments** and **wallet top-ups**, including configuration, flows, callbacks, and security practices.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Environment & Configuration](#2-environment--configuration)
3. [Payment Flows](#3-payment-flows)
4. [Callback URLs](#4-callback-urls)
5. [Security Model](#5-security-model)
6. [Payment Link Types (Invoice vs Session Redirect)](#6-payment-link-types-invoice-vs-session-redirect)
7. [Order Payment Flow (Step-by-Step)](#7-order-payment-flow-step-by-step)
8. [Wallet Charge Flow](#8-wallet-charge-flow)
9. [Verification & Idempotency](#9-verification--idempotency)
10. [Troubleshooting](#10-troubleshooting)
11. [References](#11-references)

---

## 1. Overview

- **Provider:** [Upayments](https://upayments.com/)  
- **Use cases:**  
  - **Order payments:** Create a payment link for an order (invoice); customer pays via the link; webhook/success callback updates payment and invoice status.  
  - **Wallet charges:** Create a top-up payment link; customer pays; webhook/success callback credits the wallet.  
- **Implementation:**  
  - `App\Services\UpaymentsService` — calls Upayments Charge API and Get Payment Status API.  
  - `App\Http\Controllers\Api\Admin\PaymentController` — handles success/cancel/notification callbacks (order and wallet).  
  - Payment and invoice updates are driven by **verified** status from the Get Payment Status API (or, when disabled, from redirect params only).

---

## 2. Environment & Configuration

### 2.1 Required `.env` Variables

| Variable | Description | Example (Sandbox) | Example (Live) |
|----------|-------------|-------------------|----------------|
| `UPAYMENTS_API_KEY` | API key (Bearer token) | `jtest123` | Your live API key |
| `UPAYMENTS_API_URL` | Base API URL (no trailing slash) | `https://sandboxapi.upayments.com/api/v1` | `https://apiv2api.upayments.com/api/v1` |

### 2.2 Optional `.env` Variables

| Variable | Description | Default |
|----------|-------------|--------|
| `UPAYMENTS_STATUS_ENDPOINT` | Path for Get Payment Status (appended to base URL) | `/api/v1/getpaymentstatus` |
| `UPAYMENTS_PAYMENT_GATEWAY_SRC` | `create-invoice` = invoice link; *empty* = session redirect (see [§6](#6-payment-link-types-invoice-vs-session-redirect)) | `create-invoice` |
| `UPAYMENTS_NOTIFICATION_TYPE` | When using create-invoice: `link`, `email`, `sms`, or `all` | `link` |
| `UPAYMENTS_VERIFY_VIA_STATUS_API` | If `true`, success/webhook use Get Payment Status before updating DB; if `false` (e.g. local), success can use redirect params only | `true` |
| `UPAYMENTS_LOGGING_ENABLED` | Enable request/response and warning logs | `true` |
| `UPAYMENTS_LOGGING_CHANNEL` | Log channel name (e.g. `stack`, `upayments`) | — |

### 2.3 Config Source

All values are read via `config('services.upayments.*')` in `config/services.php`. Ensure `APP_URL` in `.env` is correct so generated callback URLs (e.g. `route('payments.success')`) point to your server.

### 2.4 Example `.env` Snippets

**Sandbox (test) — invoice link:**

```env
UPAYMENTS_API_KEY=jtest123
UPAYMENTS_API_URL=https://sandboxapi.upayments.com/api/v1
UPAYMENTS_STATUS_ENDPOINT=/api/v1/getpaymentstatus
UPAYMENTS_PAYMENT_GATEWAY_SRC=create-invoice
UPAYMENTS_NOTIFICATION_TYPE=link
UPAYMENTS_VERIFY_VIA_STATUS_API=true
APP_URL=https://your-domain.com
```

**Sandbox — session redirect (e.g. `https://sandbox.upayments.com?session_id=...`):**

```env
UPAYMENTS_API_KEY=jtest123
UPAYMENTS_API_URL=https://sandboxapi.upayments.com/api/v1
UPAYMENTS_PAYMENT_GATEWAY_SRC=
```

**Live (production):**

```env
UPAYMENTS_API_KEY=your_live_api_key
UPAYMENTS_API_URL=https://apiv2api.upayments.com/api/v1
UPAYMENTS_STATUS_ENDPOINT=/api/v1/getpaymentstatus
UPAYMENTS_PAYMENT_GATEWAY_SRC=create-invoice
UPAYMENTS_NOTIFICATION_TYPE=link
UPAYMENTS_VERIFY_VIA_STATUS_API=true
APP_URL=https://your-production-domain.com
```

---

## 3. Payment Flows

- **Order payment:**  
  1. Create order (with `payment_method = online_link`).  
  2. Create invoice; call `UpaymentsService::createPayment($order, $amount)` to get payment URL.  
  3. Store URL on invoice / return in API response.  
  4. Customer opens link and pays.  
  5. Upayments redirects to success/cancel and sends a server-to-server notification (webhook).  
  6. App verifies via Get Payment Status (`track_id`), then updates `Payment` and `Invoice` (idempotent by `gateway` + `track_id`).

- **Wallet charge:**  
  1. Client requests wallet top-up (amount).  
  2. App creates a `Payment` with `type = wallet_charge` and calls `UpaymentsService::createWalletChargePayment($payment, $amount)`.  
  3. Return payment link to client.  
  4. Customer pays; Upayments calls success/cancel/notification.  
  5. App updates payment and credits wallet on success.

---

## 4. Callback URLs

Callbacks are **public** (no auth). They are registered under the `api` prefix. With `APP_URL=https://your-domain.com`, the full URLs are:

### Order payment

| Purpose | Method | URL |
|--------|--------|-----|
| Success redirect | GET | `{APP_URL}/api/payments/callback/success` |
| Cancel redirect | GET | `{APP_URL}/api/payments/callback/cancel` |
| Webhook | POST | `{APP_URL}/api/payments/callback/notification` |

### Wallet charge

| Purpose | Method | URL |
|--------|--------|-----|
| Success redirect | GET | `{APP_URL}/api/payments/callback/wallet-charge/success` |
| Cancel redirect | GET | `{APP_URL}/api/payments/callback/wallet-charge/cancel` |
| Webhook | POST | `{APP_URL}/api/payments/callback/wallet-charge/notification` |

These exact URLs are sent to Upayments as `returnUrl`, `cancelUrl`, and `notificationUrl` when creating the charge. **Upayments must be able to reach the notification URL** (public HTTPS); for local development the webhook may be unreachable, so the app supports updating from the success redirect when `UPAYMENTS_VERIFY_VIA_STATUS_API=false`.

---

## 5. Security Model

- **Success/Cancel (redirect):**  
  Used for **UI only**. The app resolves the order/wallet payment from query params and returns status (e.g. `invoice_status: paid/pending`). It does **not** trust redirect params alone to update the database when `UPAYMENTS_VERIFY_VIA_STATUS_API=true`.

- **Notification (webhook):**  
  - Raw body is stored in `payment_gateway_events` for audit.  
  - `track_id` is extracted; payment status is **verified** via `getPaymentStatus(track_id)` (Get Payment Status API).  
  - Only after a successful verification does the app update `Payment` and `Invoice` (or wallet) via `processVerifiedPayment` / wallet success handler.  
  - Updates are **idempotent** (e.g. Payment keyed by `gateway` + `track_id`; duplicate webhooks do not double-credit).

- **Sensitive data:**  
  API keys and full card data are not logged; only minimal payload and outcome are logged.

---

## 6. Payment Link Types (Invoice vs Session Redirect)

- **`UPAYMENTS_PAYMENT_GATEWAY_SRC=create-invoice`**  
  - Uses Upayments “create-invoice” flow.  
  - Response returns an **invoice URL**, e.g.:  
    - Sandbox: `https://dev-uinvoice.upayments.com/<id>`  
    - Live: `https://uinvoice.upayments.com/<id>`  
  - Requires `notificationType` (e.g. `link`); the app sends `notificationType: link` when using create-invoice.

- **`UPAYMENTS_PAYMENT_GATEWAY_SRC=` (empty)**  
  - Uses default charge flow (no create-invoice).  
  - Response can return a **session redirect** URL, e.g.:  
    - Sandbox: `https://sandbox.upayments.com?session_id=...`  
  - Use this when you want the customer to be sent to the session-based payment page instead of an invoice page.

The app extracts the payment URL from the API response (e.g. `url`, `link`, `payment_url`, `invoice_url`, `redirect_url`, etc.) and only treats a value as the link if it is a string starting with `http://` or `https://` (so numeric values like invoice IDs in `link` are ignored).

---

## 7. Order Payment Flow (Step-by-Step)

1. **Create order** (e.g. via Admin or Mobile API) with `payment_method = online_link`.
2. **Create invoice** and compute `amount_due`.
3. **Create payment link:**  
   `UpaymentsService::createPayment(Order $order, float $amount)`  
   - Builds Charge API payload (products, order, customer, `returnUrl`, `cancelUrl`, `notificationUrl`).  
   - If `payment_gateway_src` is set (e.g. `create-invoice`), adds `paymentGateway.src` and, for create-invoice, `notificationType`.  
   - POSTs to `{UPAYMENTS_API_URL}/charge`.  
   - Returns the extracted payment URL; the app stores it on the invoice and/or returns it in the order response.
4. **Customer** opens the link and completes or cancels payment on Upayments.
5. **Redirect:**  
   - Success → `GET /api/payments/callback/success?...`  
   - Cancel → `GET /api/payments/callback/cancel?...`  
   The app resolves the order from query params and returns JSON with `order_number`, `status`, `invoice_status`.  
   If `UPAYMENTS_VERIFY_VIA_STATUS_API=true` and a `track_id` is present, the app may call Get Payment Status and run `processVerifiedPayment` so that the invoice is updated even if the webhook is delayed or missed (e.g. in production).
6. **Webhook:**  
   Upayments sends `POST /api/payments/callback/notification` with payload containing `track_id`, etc.  
   - App stores raw payload in `payment_gateway_events`.  
   - App calls `getPaymentStatus(track_id)`.  
   - On success, calls `processVerifiedPayment`: update or create `Payment` by `gateway` + `track_id`, set status; if status is completed, lock invoice and mark `Invoice` as paid when total paid ≥ amount_due.  
   - Response 200 with a clear message so Upayments does not retry unnecessarily.
7. **Idempotency:**  
   If the webhook is repeated, the same `track_id` is processed again; the existing `Payment` is found and updated (or left as completed), and the invoice is not double-paid.

---

## 8. Wallet Charge Flow

1. **Request:** Client sends desired top-up amount (e.g. via Mobile API).
2. **Create charge:**  
   `WalletChargeService::createCharge($customerId, $amount)`  
   - Creates a `Payment` with `type = wallet_charge`, `reference` (e.g. WCH-YYYY-NNNNNN).  
   - Calls `UpaymentsService::createWalletChargePayment($payment, $amount)` with the same URL/callback pattern (wallet-charge success/cancel/notification).  
   - Saves returned payment URL on the payment and returns it to the client.
3. **Customer** pays or cancels via the link.
4. **Callbacks:**  
   - Success/cancel: resolve wallet payment by reference (e.g. `requested_order_id` or `reference`), return status.  
   - For success, if result is CAPTURED (or equivalent), `WalletChargeService::processSuccess` credits the wallet.  
   - Notification: same reference resolution; on success status, process success (credit wallet).  
   Duplicate notifications should be handled so the wallet is not credited twice (e.g. by tracking processed reference/transaction).

---

## 9. Verification & Idempotency

- **Get Payment Status:**  
  `UpaymentsService::getPaymentStatus(string $trackId)` calls the Get Payment Status API with the configured endpoint and API key, with timeout and retries. It returns a normalized array including `is_success`, `is_failed`, `amount`, `track_id`, `requested_order_id`, etc.

- **Order payments:**  
  - Payment record is keyed by `gateway` (e.g. `upayments`) and `track_id`.  
  - Same webhook/success verification run twice for the same `track_id` updates the same record and does not create a second payment.  
  - Invoice is updated to `paid` only after a verified completed payment and only when total completed payment amount ≥ invoice amount_due, with row lock to avoid race conditions.

- **Wallet:**  
  - Success is applied by reference; ensure your logic prevents double-crediting for the same reference or external transaction id.

---

## 10. Troubleshooting

| Issue | What to check |
|-------|----------------|
| Payment link is `"1"` or numeric | API may return invoice id in `link`. The app only uses values that look like URLs (`http://` or `https://`). Check logs for the raw response; add the correct URL key in `extractPaymentLinkFromResponse` if needed. |
| Invoice not updating after payment | Ensure notification URL is reachable from the internet (HTTPS). If webhook is unreachable (e.g. local), set `UPAYMENTS_VERIFY_VIA_STATUS_API=false` so the success redirect can trigger verification from redirect params. |
| Sandbox returns invoice URL instead of session URL | Set `UPAYMENTS_PAYMENT_GATEWAY_SRC=` (empty) to use default charge flow and get the session redirect (e.g. `https://sandbox.upayments.com?session_id=...`). |
| Live returns invoice URL | Keep `UPAYMENTS_PAYMENT_GATEWAY_SRC=create-invoice` and ensure `UPAYMENTS_NOTIFICATION_TYPE=link`. Live invoice links look like `https://uinvoice.upayments.com/...`. |
| Webhook returns 502 | Get Payment Status call failed (timeout, wrong endpoint, or invalid API key). Check `UPAYMENTS_API_URL`, `UPAYMENTS_STATUS_ENDPOINT`, and `UPAYMENTS_API_KEY`; check logs for the exact error. |
| Callbacks 404 | Ensure `APP_URL` is correct and routes are registered (`php artisan route:list | grep payments`). Callbacks live under `/api/payments/callback/...`. |

Logs (when `UPAYMENTS_LOGGING_ENABLED=true`) go to the configured log channel; check `storage/logs` for Upayments requests, responses, and warnings.

---

## 11. References

- [Upayments API Reference](https://developers.upayments.com/reference)  
- [Make charge (addcharge)](https://developers.upayments.com/reference/addcharge)  
- [Create Invoice](https://developers.upayments.com/reference/create-invoice)  
- [Get Payment Status](https://developers.upayments.com/reference/checkpaymentstatus) (or equivalent in your contract)  
- Application code: `App\Services\UpaymentsService`, `App\Http\Controllers\Api\Admin\PaymentController`, `App\Services\WalletChargeService`, `App\Services\OrderService` (order + invoice creation), `config/services.php` (upayments section)
