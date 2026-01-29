# Architecture Documentation

## Overview

This document describes the architecture of the Invoicing System, which follows Domain-Driven Design (DDD), Hexagonal Architecture, CQRS, and Event-Driven Architecture principles.

## Architectural Layers

### 1. Domain Layer (`src/Domain/`)

The Domain layer contains the core business logic and is completely independent of other layers.

**Components:**
- **Aggregates**: `Invoice` - The main aggregate root
- **Value Objects**: `InvoiceId`, `InvoiceNumber`, `InvoiceStatus`, `Money`, `Uuid`
- **Domain Events**: `InvoiceCreated`, `InvoiceItemAdded`, `InvoiceItemRemoved`, `InvoiceItemQuantityUpdated`
- **Repositories (Interfaces)**: `InvoiceRepository` - Port definition

**Principles:**
- No dependencies on other layers
- Rich domain model with business logic
- Immutable value objects
- Domain events for side effects

### 2. Application Layer (`src/Application/`)

The Application layer orchestrates domain objects to fulfill use cases.

**Components:**
- **Commands**: `CreateInvoiceCommand`, `AddInvoiceItemCommand`, `RemoveInvoiceItemCommand`, `UpdateInvoiceItemQuantityCommand`, `IssueInvoiceCommand`
- **Command Handlers**: Process commands and modify domain state
- **Queries**: `GetInvoiceQuery`, `GetInvoiceListQuery`
- **Query Handlers**: Retrieve and transform data for reading
- **View Models**: DTOs for query results

**CQRS Pattern:**
- Commands: Write operations that modify state
- Queries: Read operations that return data
- Clear separation of concerns

### 3. Infrastructure Layer (`src/Infrastructure/`)

The Infrastructure layer implements the ports defined in the Domain layer.

**Components:**
- **Persistence**: Doctrine ORM implementation of `InvoiceRepository`
- **Event Bus**: RabbitMQ implementation of `EventBus`
- **Event Listeners**: Handle domain events

**Adapters:**
- Database adapter (Doctrine)
- Message broker adapter (RabbitMQ)
- External service adapters

### 4. Presentation Layer (`src/Presentation/`)

The Presentation layer handles HTTP requests and responses.

**Components:**
- **Controllers**: REST API endpoints
- **Request/Response handling**: JSON serialization
- **OpenAPI documentation**: API specification

## Hexagonal Architecture

```
                    ┌─────────────────┐
                    │   Presentation  │
                    │    (Controllers)│
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │   Application   │
                    │  (Use Cases)    │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
┌───────▼────────┐  ┌────────▼────────┐  ┌───────▼────────┐
│   Domain       │  │ Infrastructure  │  │ Infrastructure │
│  (Business    │  │  (Persistence)  │  │  (Event Bus)   │
│   Logic)      │  │                  │  │                 │
└───────────────┘  └──────────────────┘  └─────────────────┘
```

**Ports and Adapters:**
- **Ports** (Interfaces): Defined in Domain layer
  - `InvoiceRepository` interface
  - `EventBus` interface
- **Adapters** (Implementations): In Infrastructure layer
  - `DoctrineInvoiceRepository` implements `InvoiceRepository`
  - `RabbitMQEventBus` implements `EventBus`

## CQRS Implementation

### Command Side (Write)

```
HTTP Request → Controller → Command → Command Handler → Domain → Repository → Database
```

**Flow:**
1. Controller receives HTTP POST/PUT/DELETE
2. Creates Command object
3. Dispatches to Command Handler via MessageBus
4. Handler loads aggregate from repository
5. Handler invokes domain method
6. Handler saves aggregate
7. Handler publishes domain events

### Query Side (Read)

```
HTTP Request → Controller → Query → Query Handler → Repository → View Model → JSON Response
```

**Flow:**
1. Controller receives HTTP GET
2. Creates Query object
3. Invokes Query Handler directly
4. Handler queries repository
5. Handler transforms to View Model
6. Returns View Model to controller
7. Controller serializes to JSON

## Event-Driven Architecture

### Event Flow

```
Domain Operation → Domain Event → Event Bus → RabbitMQ → Event Listeners
```

**Example: Invoice Creation**

1. `Invoice::create()` is called
2. Domain event `InvoiceCreated` is recorded
3. Command handler publishes event via `EventBus`
4. `RabbitMQEventBus` dispatches to RabbitMQ
5. Event listeners (e.g., `InvoiceCreatedListener`) handle the event

### Event Types

- **Domain Events**: `InvoiceCreated`, `InvoiceItemAdded`, `InvoiceItemRemoved`, `InvoiceItemQuantityUpdated`
- **Event Listeners**: Handle events asynchronously
- **Event Bus**: Abstraction for event publishing

## SOLID Principles

### Single Responsibility Principle (SRP)
- Each class has one reason to change
- Controllers handle HTTP, Handlers handle business logic
- Repositories handle persistence

### Open/Closed Principle (OCP)
- Open for extension, closed for modification
- New event listeners can be added without changing existing code
- New query handlers can be added independently

### Liskov Substitution Principle (LSP)
- Repository implementations are interchangeable
- Event bus implementations are interchangeable

### Interface Segregation Principle (ISP)
- Small, focused interfaces
- `InvoiceRepository` only contains invoice-related methods
- `EventBus` only contains event publishing methods

### Dependency Inversion Principle (DIP)
- High-level modules depend on abstractions
- Domain defines interfaces (ports)
- Infrastructure implements interfaces (adapters)

## Data Flow

### Creating an Invoice

```
1. POST /api/invoices
   ↓
2. InvoiceController::create()
   ↓
3. CreateInvoiceCommand
   ↓
4. CreateInvoiceCommandHandler
   ↓
5. Invoice::create() (Domain)
   ↓
6. InvoiceCreated event recorded
   ↓
7. InvoiceRepository::save()
   ↓
8. EventBus::publish(InvoiceCreated)
   ↓
9. RabbitMQ → InvoiceCreatedListener
```

### Querying an Invoice

```
1. GET /api/invoices/{id}
   ↓
2. InvoiceController::get()
   ↓
3. GetInvoiceQuery
   ↓
4. GetInvoiceQueryHandler
   ↓
5. InvoiceRepository::findById()
   ↓
6. InvoiceViewModel::fromDomain()
   ↓
7. JSON Response
```

## Database Schema

### Invoices Table

```sql
CREATE TABLE invoices (
    id UUID PRIMARY KEY,
    number VARCHAR(255) UNIQUE NOT NULL,
    customer_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    items JSON NOT NULL,
    total_amount DOUBLE PRECISION NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

**Indexes:**
- `idx_invoices_customer_id` on `customer_id`
- `idx_invoices_status` on `status`
- `idx_invoices_number` on `number`

## Technology Decisions

### Why Symfony 7.3?
- Modern PHP 8.3 features
- Excellent dependency injection
- Built-in CQRS support via Messenger
- Strong ecosystem
- Latest stable version with security patches
- Better compatibility with testing tools

### Why PostgreSQL?
- ACID compliance
- JSON support for flexible data
- UUID support
- Excellent performance

### Why RabbitMQ?
- Reliable message delivery
- Event-driven architecture support
- Decoupling of components
- Scalability

### Why CQRS?
- Clear separation of reads and writes
- Optimized for different use cases
- Scalability
- Maintainability

## Future Enhancements

1. **Read Models**: Separate read-optimized database
2. **Event Sourcing**: Store all domain events
3. **Saga Pattern**: For distributed transactions
4. **API Gateway**: For microservices architecture
5. **GraphQL**: Alternative to REST API
