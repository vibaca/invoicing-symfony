# Design Decisions Documentation

This document explains the key design decisions made during the development of the Invoicing System.

## 1. Why Domain-Driven Design (DDD)?

### Decision
Use DDD as the primary architectural approach.

### Rationale
- **Rich Domain Model**: Business logic is encapsulated in domain entities, making the code more expressive and maintainable
- **Ubiquitous Language**: Domain experts and developers share the same vocabulary
- **Bounded Contexts**: Clear boundaries between different parts of the system
- **Aggregates**: Invoice is an aggregate root, ensuring consistency boundaries

### Trade-offs
- **Pros**: Better alignment with business, easier to maintain, testable
- **Cons**: More initial complexity, requires domain knowledge

## 2. Why Hexagonal Architecture?

### Decision
Implement Hexagonal Architecture (Ports and Adapters).

### Rationale
- **Decoupling**: Domain layer has no dependencies on infrastructure
- **Testability**: Easy to mock ports for testing
- **Flexibility**: Can swap implementations (e.g., change database, message broker)
- **Clear Boundaries**: Explicit separation of concerns

### Implementation
- **Ports**: Interfaces in Domain layer (`InvoiceRepository`, `EventBus`)
- **Adapters**: Implementations in Infrastructure layer (`DoctrineInvoiceRepository`, `RabbitMQEventBus`)

## 3. Why CQRS?

### Decision
Separate Commands (writes) from Queries (reads).

### Rationale
- **Scalability**: Can scale read and write sides independently
- **Optimization**: Different models for reads and writes
- **Clarity**: Clear separation of concerns
- **Future-Proof**: Easy to add read models, caching, etc.

### Implementation
- **Commands**: `CreateInvoiceCommand`, `AddInvoiceItemCommand`, etc.
- **Queries**: `GetInvoiceQuery`, `GetInvoiceListQuery`
- **Handlers**: Separate handlers for commands and queries

## 4. Why Event-Driven Architecture?

### Decision
Use domain events and RabbitMQ for asynchronous processing.

### Rationale
- **Decoupling**: Components don't need to know about each other
- **Scalability**: Events can be processed asynchronously
- **Extensibility**: Easy to add new event handlers
- **Reliability**: RabbitMQ ensures message delivery

### Implementation
- **Domain Events**: `InvoiceCreated`, `InvoiceItemAdded`
- **Event Bus**: Interface in Domain, RabbitMQ implementation in Infrastructure
- **Event Listeners**: Handle events asynchronously

## 5. Why Symfony 7.3?

### Decision
Use Symfony 7.3 as the framework.

### Rationale
- **Modern PHP**: PHP 8.3 features
- **Messenger Component**: Built-in support for CQRS and events
- **Dependency Injection**: Excellent DI container
- **Ecosystem**: Rich set of bundles and tools
- **Community**: Large, active community
- **Security**: Latest stable version with security patches
- **Compatibility**: Better compatibility with current tooling (Behat, PHPStan, etc.)

## 6. Why PostgreSQL?

### Decision
Use PostgreSQL as the database.

### Rationale
- **ACID Compliance**: Ensures data integrity
- **JSON Support**: Store invoice items as JSON
- **UUID Support**: Native UUID type
- **Performance**: Excellent for complex queries
- **Open Source**: No licensing costs

### Alternative Considered
- **MySQL**: Less robust JSON support, no native UUID
- **MongoDB**: No ACID guarantees, different query model

## 7. Why RabbitMQ?

### Decision
Use RabbitMQ for message queuing.

### Rationale
- **Reliability**: Guaranteed message delivery
- **Flexibility**: Multiple exchange types, routing
- **Management UI**: Easy monitoring and debugging
- **Symfony Integration**: Native Messenger support
- **Scalability**: Can handle high message volumes

### Alternative Considered
- **Redis**: Faster but less reliable, no management UI
- **Kafka**: More complex, overkill for this use case

## 8. Why Value Objects?

### Decision
Use value objects for domain concepts (Money, InvoiceNumber, etc.).

### Rationale
- **Immutability**: Cannot be changed after creation
- **Validation**: Encapsulates validation logic
- **Type Safety**: Prevents primitive obsession
- **Expressiveness**: Code is more readable

### Examples
- `Money`: Encapsulates amount and currency
- `InvoiceNumber`: Validates format and uniqueness
- `InvoiceStatus`: Type-safe status values

## 9. Why Repository Pattern?

### Decision
Use Repository pattern for data access.

### Rationale
- **Abstraction**: Domain doesn't know about persistence
- **Testability**: Easy to mock repositories
- **Flexibility**: Can change persistence implementation
- **DDD Alignment**: Standard DDD pattern

### Implementation
- **Interface**: `InvoiceRepository` in Domain layer
- **Implementation**: `DoctrineInvoiceRepository` in Infrastructure layer

## 10. Why View Models?

### Decision
Use View Models for query results instead of returning domain entities.

### Rationale
- **Separation**: Queries don't expose domain internals
- **Optimization**: Can include computed fields, denormalized data
- **API Design**: Clean, focused API responses
- **CQRS Alignment**: Different models for reads

### Implementation
- `InvoiceViewModel`: Transforms domain entity to DTO
- `InvoiceListViewModel`: Includes pagination metadata

## 11. Why Docker?

### Decision
Dockerize the entire application.

### Rationale
- **Consistency**: Same environment for all developers
- **Isolation**: Services don't interfere with each other
- **Easy Setup**: One command to start everything
- **Production-like**: Similar to production environment

## 12. Why PHPUnit + Behat?

### Decision
Use PHPUnit for unit tests and Behat for acceptance tests.

### Rationale
- **PHPUnit**: Industry standard for PHP unit testing
- **Behat**: BDD approach, readable scenarios
- **Coverage**: Unit tests for logic, acceptance tests for flows
- **Integration**: Both work well with Symfony

### Implementation
- **Behat Extensions**: Using `friends-of-behat/symfony-extension` and `friends-of-behat/mink-extension` (compatible with Symfony 7)
- **Configuration**: `behat.yml` configured for Symfony 7 with proper extension namespaces

## 13. Why PHPStan + PHP CodeSniffer?

### Decision
Use both static analysis tools.

### Rationale
- **PHPStan**: Catches type errors, null pointer issues
- **PHP CodeSniffer**: Enforces coding standards (PSR-12)
- **Quality**: Maintains high code quality
- **CI/CD**: Can be integrated into pipelines

## 14. Why OpenAPI/Swagger?

### Decision
Use OpenAPI for API documentation.

### Rationale
- **Interactive**: Test API directly from documentation
- **Standard**: Industry-standard format
- **Auto-generated**: Documentation stays in sync with code
- **Client Generation**: Can generate client libraries

## 15. Why Immutable Value Objects?

### Decision
Make value objects immutable.

### Rationale
- **Thread Safety**: No race conditions
- **Predictability**: Cannot be changed unexpectedly
- **Functional Style**: Aligns with functional programming
- **Debugging**: Easier to reason about

## 16. Why Domain Events?

### Decision
Use domain events for side effects.

### Rationale
- **Decoupling**: Domain doesn't know about infrastructure
- **Extensibility**: Easy to add new handlers
- **Audit Trail**: Events can be stored for auditing
- **Event Sourcing**: Foundation for event sourcing (future)

## 17. Why Separate Command and Query Handlers?

### Decision
Separate handlers for commands and queries.

### Rationale
- **CQRS**: Clear separation of concerns
- **Optimization**: Can optimize each side independently
- **Scalability**: Can scale separately
- **Maintainability**: Easier to understand and modify

## 18. Why JSON for Invoice Items?

### Decision
Store invoice items as JSON in database.

### Rationale
- **Flexibility**: Items can have different structures
- **Simplicity**: No need for separate items table
- **Performance**: Single query to load invoice with items
- **PostgreSQL**: Excellent JSON support

### Trade-off
- **Pros**: Simple, flexible, fast
- **Cons**: Harder to query individual items, no referential integrity

## 19. Why UUID for IDs?

### Decision
Use UUIDs for entity identifiers.

### Rationale
- **Uniqueness**: Globally unique
- **Security**: Harder to guess than sequential IDs
- **Distributed**: Can generate IDs without database
- **No Collisions**: Safe for distributed systems

## 20. Why Makefile?

### Decision
Provide Makefile for common commands.

### Rationale
- **Convenience**: Easy to remember commands
- **Documentation**: Commands are self-documenting
- **Consistency**: Same commands for all developers
- **CI/CD**: Can be used in pipelines

## Future Considerations

### Event Sourcing
- Store all domain events
- Rebuild state from events
- Full audit trail

### Read Models
- Separate database for queries
- Optimized for reading
- Can be denormalized

### Microservices
- Split into separate services
- API Gateway
- Service mesh

### GraphQL
- Alternative to REST
- Client-specific queries
- Better for mobile apps
