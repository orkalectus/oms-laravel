# OMS API Documentation

**Version:** 1.0.0  
**Base URL:** `http://localhost:8000/api/v1`  
**Format:** JSON  
**Authentication:** Bearer Token (Laravel Sanctum)

---

## Authentication

### Register
`POST /auth/register`

**Request Body:**
```json
{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "081234567890",
  "address": "Jl. Merdeka No. 1",
  "city": "Bandung",
  "province": "Jawa Barat",
  "postal_code": "40111",
  "city_id": 151
}
```

**Response 201:**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": { "id": 1, "name": "Budi Santoso", "email": "budi@example.com" },
    "token": "1|abcdef...",
    "token_type": "Bearer"
  }
}
```

---

### Login
`POST /auth/login`

**Request Body:**
```json
{ "email": "budi@example.com", "password": "password123" }
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "2|xyz...",
    "token_type": "Bearer"
  }
}
```

---

## Products

### List Products
`GET /products?limit=20&page=1`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": "fakestore_1",
        "external_id": 1,
        "source": "fakestore",
        "name": "Fjallraven - Foldsack No. 1 Backpack",
        "description": "...",
        "price": 109.95,
        "price_idr": 1649250,
        "category": "men's clothing",
        "image": "https://...",
        "rating": { "rate": 3.9, "count": 120 },
        "stock": 42,
        "weight": 850,
        "sku": "FSK-00001"
      }
    ],
    "count": 20
  }
}
```

### Search Products
`GET /products/search?q=laptop&limit=10`

---

## Shipping

### Calculate Shipping Cost
`POST /shipping/calculate`

**Request Body:**
```json
{
  "origin_city_id": 23,
  "destination_city_id": 151,
  "weight": 1000,
  "courier": "jne"
}
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "rates": [
      {
        "courier": "JNE",
        "service": "REG",
        "description": "JNE REG",
        "cost": 18000,
        "etd": "2-3 HARI",
        "note": "[SIMULATED]"
      },
      {
        "courier": "JNE",
        "service": "YES",
        "description": "JNE YES",
        "cost": 38000,
        "etd": "1 HARI",
        "note": "[SIMULATED]"
      }
    ],
    "origin_city_id": 23,
    "destination_city_id": 151,
    "weight_grams": 1000
  }
}
```

Set `"courier": "all"` untuk mendapatkan rates dari semua courier (JNE, J&T, SiCepat, POS, TIKI).

---

## Orders

### Create Order
`POST /orders` *(requires auth)*

> ⚠️ **Wajib** menyertakan `idempotency_key` yang unik per request. Gunakan UUID.  
> Jika request duplikat dengan key yang sama dikirim, order yang sudah ada akan dikembalikan (HTTP 200).

**Request Body:**
```json
{
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "items": [
    { "product_id": "fakestore_1", "quantity": 2 },
    { "product_id": "dummyjson_5", "quantity": 1 }
  ],
  "shipping": {
    "destination_city_id": 151,
    "courier": "jne",
    "service": "REG",
    "recipient_name": "Budi Santoso",
    "recipient_phone": "081234567890",
    "recipient_address": "Jl. Merdeka No. 1",
    "recipient_city": "Bandung",
    "recipient_province": "Jawa Barat",
    "recipient_postal_code": "40111"
  },
  "notes": "Please pack carefully"
}
```

**Response 201:**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "order_number": "ORD-20240115-ABC123",
    "idempotency_key": "550e8400-...",
    "status": "created",
    "subtotal": "3298500.00",
    "shipping_cost": "18000.00",
    "total_amount": "3316500.00",
    "currency": "IDR",
    "items": [
      {
        "id": 1,
        "product_id": "fakestore_1",
        "product_name": "Fjallraven Backpack",
        "product_sku": "FSK-00001",
        "unit_price": "1649250.00",
        "quantity": 2,
        "subtotal": "3298500.00",
        "product_snapshot": { "..." : "full product data saved at order time" }
      }
    ],
    "shipping": {
      "courier": "jne",
      "service": "REG",
      "cost": "18000.00",
      "recipient_name": "Budi Santoso",
      "status": "pending"
    }
  }
}
```

---

### Initiate Payment
`POST /orders/{id}/payment/initiate` *(requires auth)*

**Request Body:**
```json
{
  "payment_method": "bank_transfer",
  "bank": "bca"
}
```

**Supported payment methods:** `bank_transfer`, `credit_card`, `e_wallet`, `qris`, `convenience_store`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "payment": {
      "id": 1,
      "payment_code": "PAY-ABCDEFGHIJKL",
      "status": "pending",
      "amount": "3316500.00",
      "payment_method": "bank_transfer",
      "gateway_transaction_id": "TXN-XYZ123",
      "gateway_reference": "88771234567890",
      "expired_at": "2024-01-16T10:30:00+07:00"
    },
    "order": { "id": 1, "status": "pending_payment" }
  }
}
```

---

### Simulate Payment (Dev Only)
`POST /simulate/orders/{id}/payment/success`  
`POST /simulate/orders/{id}/payment/failure`

Hanya tersedia ketika `APP_DEBUG=true`. Digunakan untuk testing tanpa payment gateway nyata.

---

### Payment Webhook
`POST /webhooks/payment`

Dipanggil oleh payment gateway. Headers wajib:
```
X-Payment-Signature: {hmac-sha256-signature}
```

**Success payload:**
```json
{
  "transaction_id": "TXN-XYZ123",
  "transaction_status": "settlement",
  "payment_type": "bank_transfer",
  "settlement_time": "2024-01-15 10:30:00"
}
```

**Failure payload:**
```json
{
  "transaction_id": "TXN-XYZ123",
  "transaction_status": "deny",
  "status_message": "Payment declined"
}
```

---

## Error Responses

### 422 Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "items": ["The items field is required."],
    "idempotency_key": ["The idempotency key field is required."]
  }
}
```

### 401 Unauthorized
```json
{ "success": false, "message": "Unauthenticated." }
```

### 404 Not Found
```json
{ "success": false, "message": "Order not found" }
```

### 500 Server Error
```json
{ "success": false, "message": "Internal server error" }
```

---

## Order Status Transitions

| From | To | Trigger |
|------|-----|---------|
| `created` | `pending_payment` | `POST /payment/initiate` |
| `pending_payment` | `paid` | Payment webhook (success) |
| `pending_payment` | `failed` | Payment webhook (failure) |
| `paid` | `processing` | Auto via event listener |
| `processing` | `shipped` | `POST /admin/orders/{id}/ship` |
| `shipped` | `completed` | Auto when delivery confirmed |
| `created` / `pending_payment` | `cancelled` | `POST /orders/{id}/cancel` |

---

## Queue Channels

| Queue | Jobs | Deskripsi |
|-------|------|-----------|
| `payments` | `ProcessPayment` | Komunikasi ke payment gateway |
| `orders` | `ProcessOrderAfterPayment` | Update order setelah bayar |
| `notifications` | `SendEmailNotification` | Kirim email notifikasi |
