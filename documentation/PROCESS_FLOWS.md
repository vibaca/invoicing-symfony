# Process Flows Documentation

This document describes the key business processes and their flows in the Invoicing System.

## Invoice Creation Process

### Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ POST /api/invoices
       ▼
┌─────────────────┐
│ InvoiceController│
└──────┬──────────┘
       │ CreateInvoiceCommand
       ▼
┌──────────────────────┐
│ CreateInvoiceCommand │
│      Handler         │
└──────┬───────────────┘
       │
       ├─► Validate invoice number uniqueness
       │
       ├─► Invoice::create()
       │   ├─► Create Invoice aggregate
       │   ├─► Set status to DRAFT
       │   └─► Record InvoiceCreated event
       │
       ├─► InvoiceRepository::save()
       │   └─► Persist to database
       │
       └─► EventBus::publish(InvoiceCreated)
           └─► Send to RabbitMQ
               └─► InvoiceCreatedListener
                   └─► Log event / Send notification
```

### Steps

1. **Client Request**: POST to `/api/invoices` with invoice data
2. **Controller**: Validates request and creates `CreateInvoiceCommand`
3. **Command Handler**: 
   - Validates invoice number uniqueness
   - Creates `Invoice` aggregate via factory method
   - Saves to repository
   - Publishes domain events
4. **Domain**: Creates invoice in DRAFT status
5. **Persistence**: Saves invoice to database
6. **Event Publishing**: Publishes `InvoiceCreated` event
7. **Event Handling**: Event listener processes the event

### Business Rules

- Invoice number must be unique
- Invoice starts in DRAFT status
- Issue date and due date are required
- Customer ID must be provided

## Adding Item to Invoice Process

### Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ POST /api/invoices/{id}/items
       ▼
┌─────────────────┐
│ InvoiceController│
└──────┬──────────┘
       │ AddInvoiceItemCommand
       ▼
┌──────────────────────┐
│ AddInvoiceItemCommand│
│      Handler         │
└──────┬───────────────┘
       │
       ├─► InvoiceRepository::findById()
       │
       ├─► Invoice::addItem()
       │   ├─► Validate item data
       │   ├─► Add item to invoice
       │   ├─► Recalculate total
       │   └─► Record InvoiceItemAdded event
       │
       ├─► InvoiceRepository::save()
       │
       └─► EventBus::publish(InvoiceItemAdded)
           └─► Send to RabbitMQ
               └─► InvoiceItemAddedListener
                   └─► Update inventory / Statistics
```

### Steps

1. **Client Request**: POST to `/api/invoices/{id}/items` with item data
2. **Controller**: Creates `AddInvoiceItemCommand`
3. **Command Handler**:
   - Loads invoice from repository
   - Validates invoice exists
   - Adds item to invoice
   - Saves invoice
   - Publishes event
4. **Domain**: 
   - Validates item (quantity > 0, price >= 0)
   - Adds item to collection
   - Recalculates total amount
5. **Event Publishing**: Publishes `InvoiceItemAdded` event

### Business Rules

- Invoice must exist
- Quantity must be greater than zero
- Unit price cannot be negative
- Total is automatically recalculated

## Invoice Issuance Process

### Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ POST /api/invoices/{id}/issue
       ▼
┌─────────────────┐
│ InvoiceController│
└──────┬──────────┘
       │ IssueInvoiceCommand
       ▼
┌──────────────────────┐
│ IssueInvoiceCommand │
│      Handler         │
└──────┬───────────────┘
       │
       ├─► InvoiceRepository::findById()
       │
       ├─► Invoice::markAsIssued()
       │   ├─► Validate status (must be DRAFT)
       │   ├─► Validate has items
       │   └─► Change status to ISSUED
       │
       └─► InvoiceRepository::save()
```

### Steps

1. **Client Request**: POST to `/api/invoices/{id}/issue`
2. **Controller**: Creates `IssueInvoiceCommand`
3. **Command Handler**:
   - Loads invoice
   - Calls `markAsIssued()` on domain
   - Saves invoice
4. **Domain**: 
   - Validates invoice is in DRAFT status
   - Validates invoice has items
   - Changes status to ISSUED

### Business Rules

- Invoice must be in DRAFT status
- Invoice must have at least one item
- Cannot issue an empty invoice

## Invoice Payment Process

### Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ POST /api/invoices/{id}/pay
       ▼
┌─────────────────┐
│ InvoiceController│
└──────┬──────────┘
       │ MarkInvoiceAsPaidCommand
       ▼
┌──────────────────────┐
│ MarkInvoiceAsPaid    │
│   Command Handler    │
└──────┬───────────────┘
       │
       ├─► InvoiceRepository::findById()
       │
       ├─► Invoice::markAsPaid()
       │   ├─► Validate status (must be ISSUED)
       │   └─► Change status to PAID
       │
       └─► InvoiceRepository::save()
           └─► Publish InvoicePaid event
```

### Business Rules

- Invoice must be in ISSUED status
- Cannot pay a DRAFT invoice
- Payment triggers domain event

## Query Invoice Process

### Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ GET /api/invoices/{id}
       ▼
┌─────────────────┐
│ InvoiceController│
└──────┬──────────┘
       │ GetInvoiceQuery
       ▼
┌──────────────────────┐
│ GetInvoiceQuery     │
│      Handler        │
└──────┬──────────────┘
       │
       ├─► InvoiceRepository::findById()
       │
       └─► InvoiceViewModel::fromDomain()
           └─► Transform to DTO
               └─► JSON Response
```

### Steps

1. **Client Request**: GET `/api/invoices/{id}`
2. **Controller**: Creates `GetInvoiceQuery`
3. **Query Handler**:
   - Queries repository
   - Transforms domain model to view model
   - Returns view model
4. **Controller**: Serializes to JSON

## List Invoices Process

### Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ GET /api/invoices?page=1&limit=10
       ▼
┌─────────────────┐
│ InvoiceController│
└──────┬──────────┘
       │ GetInvoiceListQuery
       ▼
┌──────────────────────┐
│ GetInvoiceListQuery  │
│      Handler         │
└──────┬───────────────┘
       │
       ├─► InvoiceRepository::findAll()
       │
       ├─► Paginate results
       │
       └─► InvoiceViewModel::fromDomain() (for each)
           └─► InvoiceListViewModel
               └─► JSON Response with pagination
```

### Steps

1. **Client Request**: GET `/api/invoices?page=1&limit=10`
2. **Controller**: Creates `GetInvoiceListQuery` with pagination
3. **Query Handler**:
   - Retrieves all invoices
   - Applies pagination
   - Transforms each to view model
   - Returns list with metadata
4. **Controller**: Serializes to JSON

## Event Processing Flow

### Flow Diagram

```
Domain Operation
       │
       ▼
Domain Event Recorded
       │
       ▼
Command Handler publishes event
       │
       ▼
EventBus (Interface)
       │
       ▼
RabbitMQEventBus (Implementation)
       │
       ▼
RabbitMQ Exchange
       │
       ▼
RabbitMQ Queue
       │
       ▼
Event Listener (Async)
       │
       ├─► Log event
       ├─► Send notification
       ├─► Update read model
       └─► Trigger other processes
```

### Event Types

1. **InvoiceCreated**
   - Triggered when invoice is created
   - Listener: Logs creation, sends notification

2. **InvoiceItemAdded**
   - Triggered when item is added
   - Listener: Updates inventory, calculates statistics

3. **InvoiceIssued** (Future)
   - Triggered when invoice is issued
   - Listener: Sends invoice to customer

4. **InvoicePaid** (Future)
   - Triggered when invoice is paid
   - Listener: Updates accounting, sends receipt

## Error Handling Flow

### Flow Diagram

```
Request
   │
   ▼
Validation Error?
   ├─► Yes → 400 Bad Request
   │
   └─► No
       │
       ▼
Domain Exception?
   ├─► Yes → 400 Bad Request (with message)
   │
   └─► No
       │
       ▼
Not Found?
   ├─► Yes → 404 Not Found
   │
       └─► No
           │
           ▼
       200/201 Success
```

### Error Types

1. **Validation Errors**: Invalid input data (400)
2. **Domain Exceptions**: Business rule violations (400)
3. **Not Found**: Resource doesn't exist (404)
4. **Server Errors**: Unexpected errors (500)

## State Transitions

### Invoice Status State Machine

```
    ┌──────┐
    │ DRAFT│
    └───┬──┘
        │ markAsIssued()
        ▼
   ┌────────┐
   │ ISSUED │
   └───┬────┘
       │ markAsPaid()
       ▼
    ┌──────┐
    │ PAID │
    └──────┘
```

**Valid Transitions:**
- DRAFT → ISSUED (via `markAsIssued()`)
- ISSUED → PAID (via `markAsPaid()`)
- ISSUED → CANCELLED (future)

**Invalid Transitions:**
- DRAFT → PAID (must be issued first)
- PAID → ISSUED (cannot revert)
