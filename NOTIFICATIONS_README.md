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

### 10) General Notification (sent by admin from dashboard)

- **Where:** `app/Services/Notification/GeneralNotificationService.php`
- **Triggered when:** Admin calls `POST /api/admin/general-notifications`.
- **Recipients:** All active customers (`customers.is_active = true`).
- **Type:** `general` or `offer`
- **Data payload:** `general_notification_id`, plus `offer_id` when type is `offer`
- Delivery is queued (`DispatchGeneralNotificationJob` -> `SendGeneralNotificationChunkJob`, 200 customers per job) on the `notifications` queue (`NOTIFICATIONS_QUEUE`). The worker must listen on it, with `default` first so order/ERP jobs keep priority:
  `php artisan queue:work --queue=default,notifications`
- Pushes are sent in parallel (`NOTIFICATIONS_PUSH_CONCURRENCY`, default 50) with HTTP timeouts (`FIREBASE_CONNECT_TIMEOUT`, `FIREBASE_TIMEOUT`).
- Device tokens that FCM reports as unregistered/invalid are deleted automatically.
- Push title/body are sent in each customer's `current_language`.

## General Notifications (Admin Dashboard)

Permission slug: `general_notifications` (`view` for listing, `add` for sending).

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/admin/general-notifications` | History. Filters: `type`, `status`, `offer_id`, `search`, `per_page` |
| `POST` | `/api/admin/general-notifications` | Send to all active customers |
| `GET` | `/api/admin/general-notifications/{id}` | Details and delivery stats |

Send body:

```json
{
  "type": "offer",
  "offer_id": 12,
  "title_en": "Summer offer is live",
  "title_ar": "عرض الصيف متاح الآن",
  "message_en": "Buy 2 get 1 free, this week only.",
  "message_ar": "اشترِ 2 واحصل على 1 مجاناً، هذا الأسبوع فقط."
}
```

- `type`: `general` (no `offer_id` allowed) or `offer` (`offer_id` required).
- The offer must be active, currently within its start/end dates, and not a subscription offer.
- `status` moves `pending` -> `processing` -> `completed` (or `failed`). The response also includes `recipients_count`, `read_count`, `push_sent_count`, `push_failed_count`, `processed_chunks` / `total_chunks`.

### Mobile app handling

FCM data payload (all values are strings):

| Key | General | Offer |
| --- | --- | --- |
| `type` | `general` | `offer` |
| `notification_id` | in-app notification id (use to mark as read) | same |
| `general_notification_id` | broadcast id | same |
| `offer_id` | not present | offer id |

When the user taps a notification with `type = "offer"`, open offer details using `GET /api/mobile/offers/{offer_id}`.
The same `type` and `data.offer_id` are returned by `GET /api/mobile/notifications`, so taps from the in-app notifications list should behave the same way.

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
