# Order Creation JSON Examples

## Endpoint
`POST /api/admin/orders`

## Example 1: Basic Order (Pickup, No Offer, No Points)

```json
{
  "customer_id": 1,
  "charity_id": 2,
  "delivery_type": "pickup",
  "items": [
    {
      "variant_id": 5,
      "quantity": 2
    },
    {
      "variant_id": 8,
      "quantity": 1
    }
  ]
}
```

## Example 2: Order with Delivery

```json
{
  "customer_id": 1,
  "charity_id": 2,
  "delivery_type": "delivery",
  "items": [
    {
      "variant_id": 5,
      "quantity": 2
    }
  ],
  "delivery": {
    "payment_method": "cash",
    "delivery_address": "123 Main Street, Building 5, Apartment 10",
    "block": "Block 10",
    "street": "Main Street",
    "house_number": "123",
    "delivery_datetime": "2024-12-25 14:00:00",
    "delivery_status": "pending",
    "notes": "Please call before delivery"
  }
}
```

## Example 3: Order with Offer (Products Reward)

```json
{
  "customer_id": 1,
  "charity_id": 2,
  "source": "app",
  "status": "pending",
  "delivery_type": "pickup",
  "offer_id": 3,
  "items": [
    {
      "variant_id": 5,
      "quantity": 2,
    }
  ]
}
```

## Example 4: Order with Offer (Discount Reward)

```json
{
  "customer_id": 1,
  "charity_id": 2,
  "source": "web",
  "status": "pending",
  "delivery_type": "pickup",
  "offer_id": 4,
  "items": [
    {
      "variant_id": 5,
      "quantity": 2
    },
    {
      "variant_id": 8,
      "quantity": 1
    }
  ]
}
```

## Example 5: Order with Points Discount

```json
{
  "customer_id": 1,
  "charity_id": 2,
  "source": "app",
  "status": "pending",
  "delivery_type": "pickup",
  "used_points": 50,
  "items": [
    {
      "variant_id": 5,
      "quantity": 2
    },
    {
      "variant_id": 8,
      "quantity": 1
    }
  ]
}
```

## Example 6: Complete Order (All Features)

```json
{
  "customer_id": 1,
  "charity_id": 2,
  "source": "call_center",
  "status": "pending",
  "delivery_type": "delivery",
  "offer_id": 3,
  "offer_snapshot": {
    "offer_name": "Summer Sale",
    "discount_percentage": 15
  },
  "used_points": 100,
  "items": [
    {
      "variant_id": 5,
      "quantity": 2
    },
    {
      "variant_id": 8,
      "quantity": 1
    }
  ],
  "delivery": {
    "payment_method": "card",
    "delivery_address": "456 Business District, Tower A, Floor 5",
    "block": "Block 15",
    "street": "Business Street",
    "house_number": "456",
    "delivery_datetime": "2024-12-25 16:00:00",
    "delivery_status": "pending",
    "notes": "Leave at reception desk"
  }
}
```

## Example 7: Minimal Order (Only Required Fields)

```json
{
  "source": "call_center",
  "status": "pending",
  "delivery_type": "pickup",
  "items": [
    {
      "variant_id": 5,
      "quantity": 1
    }
  ]
}
```

## Field Descriptions

### Required Fields:
- `source`: Order source - `app` (mobile app), `web` (website), or `call_center` (admin/call center)
- `status`: Order status - `pending`, `processing`, `completed`, or `cancelled`
- `delivery_type`: `pickup` or `delivery`
- `items`: Array of order items (minimum 1 item)
  - `variant_id`: Product variant ID (required)
  - `quantity`: Quantity (required, minimum 1)
  - `is_offer`: **Auto-set** - Automatically set to `true` for offer reward products, `false` for regular items

### Optional Fields:
- `customer_id`: Customer ID (required if using points)
- `order_number`: **Auto-generated** - Format: `APPS-2025-000001`, `WEBS-2025-000001`, or `CALS-2025-000001` (based on source)
- `charity_id`: Charity ID
- `offer_id`: Active offer ID (will validate offer is active)
- `offer_snapshot`: Array of offer details (optional)
- `used_points`: Points to use for discount (minimum 10, must be multiple of 10, requires customer_id)

### Delivery Fields (Required if delivery_type is "delivery"):
- `delivery`: Object containing:
  - `delivery_address`: Full delivery address (required)
  - `payment_method`: `cash`, `card`, `online`, `bank_transfer`, or `wallet` (optional, default: cash)
  - `block`: Block number (optional)
  - `street`: Street name (optional)
  - `house_number`: House number (optional)
  - `delivery_datetime`: Preferred delivery date/time (optional, format: Y-m-d H:i:s)
  - `delivery_status`: `pending`, `assigned`, `in_transit`, `delivered`, `failed`, or `cancelled` (optional, default: pending)
  - `notes`: Delivery notes (optional)

## Order Number Generation:
- **Auto-generated** based on `source` field
- Format: `PREFIX-YEAR-SEQUENCE`
- Prefixes:
  - `app` → `APPS-2025-000001`
  - `web` → `WEBS-2025-000001`
  - `call_center` → `CALS-2025-000001`
- Sequence resets each year
- Sequence is 6 digits with leading zeros
- Example: First order from app in 2025 = `APPS-2025-000001`, second = `APPS-2025-000002`

## Points Discount Rules:
- Minimum: 10 points
- Exchange rate: 10 points = 1 dinar
- Must be multiple of 10
- Customer must have enough points
- Discount is capped to remaining amount after offer discount

## Offer Rules:
- Offer must be active (valid dates and is_active = true)
- If reward_type is "products": Free products added to order items
- If reward_type is "discount": Discount applied to invoice
- Points from offer are added to customer when order status changes to "completed"

## Response Example:

```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "customer_id": 1,
    "charity_id": 2,
    "order_number": "ORD-2024-006",
    "status": "pending",
    "total_amount": "150.00",
    "offer_id": 3,
    "delivery_type": "delivery",
    "created_at": "2024-12-20T10:00:00.000000Z",
    "updated_at": "2024-12-20T10:00:00.000000Z",
    "customer": {
      "id": 1,
      "name": "John Doe",
      "phone": "+96512345678"
    },
    "charity": {
      "id": 2,
      "name_en": "Charity Name"
    },
    "offer": {
      "id": 3,
      "reward_type": "products"
    },
    "items": [
      {
        "id": 1,
        "variant_id": 5,
        "product_id": 2,
        "name": "Product Name - Large",
        "sku": "SKU-001",
        "quantity": 2,
        "unit_price": "50.00",
        "total_price": "100.00",
      }
    ],
    "invoice": {
      "id": 1,
      "invoice_number": "INV-CALS-2025-000001",
      "amount_due": "45.00",
      "offer_discount": "10.00",
      "used_points": 100,
      "points_discount": "10.00",
      "total_discount": "20.00",
      "status": "pending"
    },
    "delivery": {
      "id": 1,
      "delivery_address": "456 Business District, Tower A, Floor 5",
      "payment_method": "card",
      "delivery_status": "pending"
    }
  }
}
```

