# API Specification

This document describes the REST API endpoints for the Invoicing System.

## Base URL

```
http://localhost:8080/api
```

## Authentication

Currently, the API does not require authentication. In production, implement JWT or OAuth2.

## Content Type

All requests and responses use `application/json`.

## Endpoints

### 1. Create Invoice

Creates a new invoice in DRAFT status.

**Endpoint:** `POST /api/invoices`

**Request Body:**
```json
{
  "invoiceNumber": "INV-2025-001",
  "customerId": "550e8400-e29b-41d4-a716-446655440000",
  "issueDate": "2025-01-25",
  "dueDate": "2025-02-25"
}
```

**Response:** `201 Created`
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "message": "Invoice created successfully"
}
```

**Validation Rules:**
- `invoiceNumber`: Required, string, must be unique
- `customerId`: Required, string (UUID format)
- `issueDate`: Required, date format (YYYY-MM-DD)
- `dueDate`: Required, date format (YYYY-MM-DD), must be after issueDate

**Errors:**
- `400 Bad Request`: Validation errors or duplicate invoice number
- `500 Internal Server Error`: Server error

---

### 2. Get Invoice

Retrieves an invoice by ID.

**Endpoint:** `GET /api/invoices/{id}`

**Path Parameters:**
- `id`: Invoice UUID

**Response:** `200 OK`
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "number": "INV-2025-001",
  "customerId": "550e8400-e29b-41d4-a716-446655440001",
  "status": "draft",
  "issueDate": "2025-01-25",
  "dueDate": "2025-02-25",
  "totalAmount": 199.98,
  "currency": "USD",
  "items": [
    {
      "productId": "550e8400-e29b-41d4-a716-446655440002",
      "description": "Product description",
      "quantity": 2,
      "unitPrice": 99.99,
      "totalPrice": 199.98,
      "currency": "USD"
    }
  ],
  "createdAt": "2025-01-25 10:00:00",
  "updatedAt": "2025-01-25 10:00:00"
}
```

**Errors:**
- `404 Not Found`: Invoice not found
- `400 Bad Request`: Invalid UUID format

---

### 3. List Invoices

Retrieves a paginated list of invoices.

**Endpoint:** `GET /api/invoices`

**Query Parameters:**
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 10)

**Example:** `GET /api/invoices?page=1&limit=10`

**Response:** `200 OK`
```json
{
  "invoices": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "number": "INV-2025-001",
      "customerId": "550e8400-e29b-41d4-a716-446655440001",
      "status": "draft",
      "issueDate": "2025-01-25",
      "dueDate": "2025-02-25",
      "totalAmount": 199.98,
      "currency": "USD",
      "items": [],
      "createdAt": "2025-01-25 10:00:00",
      "updatedAt": "2025-01-25 10:00:00"
    }
  ],
  "total": 1,
  "page": 1,
  "limit": 10,
  "totalPages": 1
}
```

**Errors:**
- `400 Bad Request`: Invalid pagination parameters

---

### 4. Add Item to Invoice

Adds an item to an existing invoice.

**Endpoint:** `POST /api/invoices/{id}/items`

**Path Parameters:**
- `id`: Invoice UUID

**Request Body:**
```json
{
  "productId": "550e8400-e29b-41d4-a716-446655440002",
  "description": "Product description",
  "quantity": 2,
  "unitPrice": 99.99
}
```

**Response:** `200 OK`
```json
{
  "message": "Item added successfully"
}
```

**Validation Rules:**
- `productId`: Required, string (UUID format)
- `description`: Required, string
- `quantity`: Required, integer, must be > 0
- `unitPrice`: Required, number, must be >= 0

**Errors:**
- `400 Bad Request`: Validation errors or invoice not found
- `404 Not Found`: Invoice not found

---

### 5. Remove Item from Invoice

Removes an item from a draft invoice by index.

**Endpoint:** `DELETE /api/invoices/{id}/items/{itemIndex}`

**Path Parameters:**
- `id`: Invoice UUID
- `itemIndex`: Zero-based index of the item to remove

**Response:** `200 OK`
```json
{
  "message": "Item removed successfully"
}
```

**Business Rules:**
- Invoice must be in DRAFT status
- `itemIndex` must be within the items list bounds

**Errors:**
- `400 Bad Request`: Invoice not in draft, or invalid index (e.g. "Can only remove items from draft invoices", "Item index out of bounds")
- `404 Not Found`: Invoice not found

---

### 6. Update Item Quantity

Updates the quantity of an item in a draft invoice.

**Endpoint:** `PATCH /api/invoices/{id}/items/{itemIndex}`

**Path Parameters:**
- `id`: Invoice UUID
- `itemIndex`: Zero-based index of the item to update

**Request Body:**
```json
{
  "quantity": 5
}
```

**Response:** `200 OK`
```json
{
  "message": "Item quantity updated successfully"
}
```

**Validation Rules:**
- `quantity`: Required, integer, must be > 0

**Business Rules:**
- Invoice must be in DRAFT status
- `itemIndex` must be within the items list bounds
- Total amount is recalculated automatically

**Errors:**
- `400 Bad Request`: Invoice not in draft, invalid quantity or index (e.g. "Can only update item quantity in draft invoices", "Quantity must be greater than zero", "Item index out of bounds")
- `404 Not Found`: Invoice not found

---

### 7. Issue Invoice

Changes invoice status from DRAFT to ISSUED.

**Endpoint:** `POST /api/invoices/{id}/issue`

**Path Parameters:**
- `id`: Invoice UUID

**Response:** `200 OK`
```json
{
  "message": "Invoice issued successfully"
}
```

**Business Rules:**
- Invoice must be in DRAFT status
- Invoice must have at least one item

**Errors:**
- `400 Bad Request`: Invoice cannot be issued (no items, wrong status)
- `404 Not Found`: Invoice not found

---

## Status Codes

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `400 Bad Request`: Invalid request data or business rule violation
- `404 Not Found`: Resource not found
- `500 Internal Server Error`: Server error

## Error Response Format

All error responses follow this format:

```json
{
  "error": "Error message",
  "errors": {
    "field": ["Error message for field"]
  }
}
```

## Invoice Status Values

- `draft`: Invoice is being created
- `issued`: Invoice has been issued to customer
- `paid`: Invoice has been paid
- `cancelled`: Invoice has been cancelled

## Status Transitions

- `draft` → `issued`: Via issue endpoint
- `issued` → `paid`: Via payment endpoint (future)
- `issued` → `cancelled`: Via cancel endpoint (future)

## OpenAPI Documentation

Interactive API documentation is available at:

```
http://localhost:8080/api/doc
```

This provides:
- Complete API reference
- Interactive testing
- Request/response examples
- Schema definitions

## Rate Limiting

Currently, there is no rate limiting. In production, implement rate limiting to prevent abuse.

## Versioning

API versioning is not currently implemented. For future versions, consider:
- URL versioning: `/api/v1/invoices`
- Header versioning: `Accept: application/vnd.api+json;version=1`

## Pagination

List endpoints support pagination:
- `page`: Page number (1-based)
- `limit`: Items per page (max: 100)

Response includes:
- `total`: Total number of items
- `page`: Current page
- `limit`: Items per page
- `totalPages`: Total number of pages

## Filtering and Sorting

Currently not implemented. Future enhancements:
- Filter by status: `?status=draft`
- Filter by customer: `?customerId=xxx`
- Sort by date: `?sort=createdAt&order=desc`
- Date range: `?from=2025-01-01&to=2025-01-31`

## Examples

### Complete Invoice Workflow

```bash
# 1. Create invoice
curl -X POST http://localhost:8080/api/invoices \
  -H "Content-Type: application/json" \
  -d '{
    "invoiceNumber": "INV-2025-001",
    "customerId": "550e8400-e29b-41d4-a716-446655440000",
    "issueDate": "2025-01-25",
    "dueDate": "2025-02-25"
  }'

# Response: {"id": "...", "message": "Invoice created successfully"}

# 2. Add item
curl -X POST http://localhost:8080/api/invoices/{id}/items \
  -H "Content-Type: application/json" \
  -d '{
    "productId": "550e8400-e29b-41d4-a716-446655440001",
    "description": "Product 1",
    "quantity": 2,
    "unitPrice": 99.99
  }'

# 3. Update item quantity (draft only)
curl -X PATCH http://localhost:8080/api/invoices/{id}/items/0 \
  -H "Content-Type: application/json" \
  -d '{"quantity": 5}'

# 4. Remove item (draft only)
# curl -X DELETE http://localhost:8080/api/invoices/{id}/items/0

# 5. Issue invoice
curl -X POST http://localhost:8080/api/invoices/{id}/issue

# 6. Get invoice
curl http://localhost:8080/api/invoices/{id}
```
