# Notifications Trigger Cases

This file documents all current business cases where `sendNotification()` is triggered.

## Overview

- Notifications are stored in:
  - `notifications` (main metadata)
  - `notification_translations` (localized content per locale: `en`, `ar`)
- Customer-facing display language is based on `customers.current_language` (`en` or `ar`).
- When a notification is created, both English and Arabic translations are stored.

## Trigger Cases

### 1) Order Created

- **Where:** `app/Services/OrderService.php` (`createOrder`)
- **Triggered when:** A new order is created successfully.
- **Recipients:**
  - Customer (if order has `customer_id`)
  - All active admins
- **Type:** `order`
- **Data payload:** `order_id`, `order_number`, `status`

### 2) Order Status Updated

- **Where:** `app/Services/OrderService.php` (`updateOrder`)
- **Triggered when:** Order status changes (old status != new status).
- **Recipients:**
  - Customer (if order has `customer_id`)
  - All active admins
- **Type:** `order`
- **Data payload:** `order_id`, `order_number`, `old_status`, `new_status`

### 3) Order Cancelled

- **Where:** `app/Services/OrderCancellationService.php` (`cancelOrder`)
- **Triggered when:** Order cancellation succeeds.
- **Recipients:**
  - Customer (if order has `customer_id`)
  - All active admins
- **Type:** `order`
- **Data payload:** `order_id`, `order_number`, `status=cancelled`

### 4) Refund Request Created (from cancellation)

- **Where:** `app/Services/OrderCancellationService.php` (`cancelOrder`)
- **Triggered when:** Cancelled paid online-link order creates a refund request.
- **Recipients:** All active admins
- **Type:** `payment`
- **Data payload:** `refund_request_id`, `order_id`, `invoice_id`

### 5) Refund Approved

- **Where:** `app/Services/RefundRequestService.php` (`approve`)
- **Triggered when:** Admin approves a pending refund request.
- **Recipients:** Customer
- **Type:** `payment`
- **Data payload:** `refund_request_id`, `order_id`, `invoice_id`, `amount`, `status=approved`

### 6) Refund Rejected

- **Where:** `app/Services/RefundRequestService.php` (`reject`)
- **Triggered when:** Admin rejects a pending refund request.
- **Recipients:** Customer
- **Type:** `payment`
- **Data payload:** `refund_request_id`, `order_id`, `invoice_id`, `amount`, `status=rejected`

### 7) Wallet Charged Successfully

- **Where:** `app/Services/WalletChargeService.php` (`processSuccess`)
- **Triggered when:** Wallet charge payment is completed.
- **Recipients:** Customer
- **Type:** `payment`
- **Data payload:** `payment_id`, `reference`, `amount`, `bonus_amount`, `total_amount`

### 8) Order Payment Verified as Completed

- **Where:** `app/Http/Controllers/Api/Admin/PaymentController.php` (`processVerifiedPayment`)
- **Triggered when:** Upayments verified status resolves to `completed`.
- **Recipients:** Customer
- **Type:** `payment`
- **Data payload:** `order_id`, `order_number`, `invoice_id`, `payment_id`, `status`

### 9) Order Payment Verified as Failed

- **Where:** `app/Http/Controllers/Api/Admin/PaymentController.php` (`processVerifiedPayment`)
- **Triggered when:** Upayments verified status resolves to `failed`.
- **Recipients:** Customer
- **Type:** `payment`
- **Data payload:** `order_id`, `order_number`, `invoice_id`, `payment_id`, `status`

## Language Behavior

- Preferred customer language is updated through:
  - `PATCH /api/mobile/profile/language`
  - body: `{ "current_language": "en" | "ar" }`
- Notification resources resolve the displayed `title` and `message` from translations using locale preference, with fallback to English.

## Helper Behavior Summary

- `sendNotification(null, null, ...)` => sends to all active admins.
- `sendNotification(adminId, null, ...)` => sends to one admin.
- `sendNotification(null, customerId, ...)` => sends to one customer.
- In all cases above, translation rows are created for both `en` and `ar`.
