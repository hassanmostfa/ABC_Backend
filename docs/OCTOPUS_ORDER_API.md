# Octopus — Create order API

Short reference for integrating with the ABC backend. Share the **base URL** and **`OCTOPUS_API_TOKEN`** with Octopus through your usual secure channel (not in email plain text if possible).

---

## Endpoint

| Item | Value |
|------|--------|
| **Method** | `POST` |
| **Path** | `/api/octopus/orders` |
| **Full URL** | `{BASE_URL}/api/octopus/orders` |

Example: `https://your-domain.com/api/octopus/orders`

---

## Authentication (required)

The server expects a shared secret configured as **`OCTOPUS_API_TOKEN`** on our side. The token **must start with** `abc_`.

Send **one** of:

| Header | Example |
|--------|---------|
| `Authorization` | `Bearer abc_your_secret_token_here` |

**Responses**

| HTTP | Meaning |
|------|---------|
| `401` | Missing / wrong token |
| `503` | Server not configured (`OCTOPUS_API_TOKEN` missing or invalid format) |

---

## Request body (JSON)

### Required

| Field | Type | Description |
|-------|------|-------------|
| `phone` | string (max 20) | Customer phone (used to find existing customer or create a new one) |
| `name` | string (max 255) | Customer name |
| `payment_method` | string | `cash` or `online_link` |
| `address` | string (max 1000) | Full delivery address text |

### Optional

| Field | Type | Description |
|-------|------|-------------|
| `src` | string | Payment gateway hint: `knet`, `cc`, or `octopus`. **Default if omitted: `octopus`** |
| `offers` | array | Promotions; see below |
| `items` | array | Cart lines; see below |
| `payment_info` | object | **Only when** `payment_method` is `online_link` **and** payment is already completed — see below |

### Line items (`items`)

Each element:

| Field | Type | Description |
|-------|------|-------------|
| `short_item` | string | **ERP short code** (not internal variant ID). Must exist in our catalog. |
| `quantity` | integer (≥ 1) | Quantity |

**Rule:** You must send **either** at least one offer **or** at least one line in `items` (same as our other order APIs).

### Offers (`offers`)

Each element:

| Field | Type | Description |
|-------|------|-------------|
| `offer_id` | integer | Our offer ID |
| `quantity` | integer (≥ 1) | How many times to apply the offer |

---

### Payment: cash (`payment_method`: `cash`)

- Order is created as **cash on delivery**.
- Invoice stays **pending** until collected in the normal process.
- Order is sent to **ERP** right after creation.

No `payment_info` needed.

---

### Payment: online (`payment_method`: `online_link`)

**A — Payment already completed (Octopus / gateway already charged)**

Send `payment_info` so we mark the invoice **paid** and store a **payment** row:

| Field | Type | Description |
|-------|------|-------------|
| `transaction_id` | string | Optional — alias used if `tran_id` empty |
| `tran_id` | string | Optional |
| `track_id` | string | Optional |
| `payment_id` | string | Optional |
| `receipt_id` | string | Optional |
| `paid_at` | date/datetime | Optional — when payment was taken |

Order is sent to **ERP** after creation (same as other paid online orders).

**B — Payment not done yet (customer pays via our link)**

Omit `payment_info`. Response includes **`payment_link`** (Upayments). Customer completes payment there; our system updates the invoice and **ERP** when payment succeeds.

---

## Success response (`201 Created`)

Shape (simplified):

```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": { "...": "order payload (id, order_number, items, invoice, ...)" },
    "customer_created": false,
    "payment_link": "https://..."
  }
}
```

- **`customer_created`:** `true` if we created a new customer from `phone` / `name`.
- **`payment_link`:** Present only for `online_link` **without** `payment_info` (pay-by-link flow).

**Order number format:** `OCT-0001`, `OCT-0002`, …

**Delivery:** Orders from this API are treated as **delivery**; **delivery date** and **delivery time** are set to **now** at creation.

---

## Error responses

| HTTP | Typical cause |
|------|----------------|
| `422` | Validation failed (`errors` object with field messages) |
| `401` / `503` | Auth / server configuration (see above) |
| `500` | Server or business rule error (`message` describes it) |

Examples: minimum order amount not met, unknown `short_item`, insufficient stock.

---

## Operational notes for Octopus

1. **Base URL** — Use the production (or staging) URL we provide.
2. **Token** — Keep `OCTOPUS_API_TOKEN` secret; rotate by updating our `.env` and sharing the new value securely.
3. **`short_item`** — Must match our product variant **short item** codes (used for ERP and pricing).
4. **ERP** — Cash orders and **paid** online orders are pushed to ERP as configured on our side; unpaid online orders are pushed after the customer pays via the returned link.

---

## Minimal JSON examples

**Cash**

```json
{
  "phone": "96550000000",
  "name": "Ahmad",
  "payment_method": "cash",
  "address": "Block 1, Street 2, Area, Kuwait",
  "items": [
    { "short_item": "YOUR_SKU_HERE", "quantity": 2 }
  ]
}
```

**Online, already paid**

```json
{
  "phone": "96550000000",
  "name": "Ahmad",
  "payment_method": "online_link",
  "address": "Block 1, Street 2, Area, Kuwait",
  "src": "octopus",
  "items": [
    { "short_item": "YOUR_SKU_HERE", "quantity": 1 }
  ],
  "payment_info": {
    "tran_id": "GATEWAY_TRAN_ID",
    "track_id": "GATEWAY_TRACK_ID",
    "paid_at": "2026-04-14 15:30:00"
  }
}
```

**cURL (with Bearer token)**

```bash
curl -X POST "https://YOUR_BASE_URL/api/octopus/orders" \
  -H "Authorization: Bearer abc_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"phone":"96550000000","name":"Ahmad","payment_method":"cash","address":"Kuwait","items":[{"short_item":"YOUR_SKU_HERE","quantity":1}]}'
```
