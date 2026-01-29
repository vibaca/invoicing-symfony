# Documentation Index

This folder contains comprehensive documentation for the Invoicing System.

## Documents

### [ARCHITECTURE.md](./ARCHITECTURE.md)
Complete architecture documentation including:
- Layer descriptions (Domain, Application, Infrastructure, Presentation)
- Hexagonal Architecture explanation
- CQRS implementation details
- Event-Driven Architecture flow
- SOLID principles application
- Technology decisions

### [PROCESS_FLOWS.md](./PROCESS_FLOWS.md)
Detailed process flow documentation:
- Invoice creation process
- Adding items to invoices
- Invoice issuance process
- Query processes
- Event processing flows
- Error handling
- State transitions

### [DESIGN_DECISIONS.md](./DESIGN_DECISIONS.md)
Rationale for key design decisions:
- Why DDD, Hexagonal Architecture, CQRS
- Technology choices (Symfony, PostgreSQL, RabbitMQ)
- Patterns and practices
- Trade-offs and alternatives
- Future considerations

### [API_SPECIFICATION.md](./API_SPECIFICATION.md)
Complete API reference:
- All endpoints with examples
- Request/response formats
- Validation rules
- Error handling
- Status codes
- Usage examples

## Quick Reference

### Architecture Layers

```
Domain (Business Logic)
    ↓
Application (Use Cases, CQRS)
    ↓
Infrastructure (Adapters)
    ↓
Presentation (REST API)
```

### Key Patterns

- **DDD**: Domain-Driven Design
- **Hexagonal**: Ports and Adapters
- **CQRS**: Command Query Responsibility Segregation
- **Event-Driven**: Domain events via RabbitMQ
- **Repository**: Data access abstraction
- **Value Objects**: Immutable domain concepts

### Technology Stack

- **Framework**: Symfony 8.0
- **PHP**: 8.3
- **Database**: PostgreSQL 16
- **Message Broker**: RabbitMQ 3
- **Testing**: PHPUnit, Behat
- **Static Analysis**: PHPStan, PHP CodeSniffer

## Study Guide

### For Learning DDD

1. Start with [ARCHITECTURE.md](./ARCHITECTURE.md) - Understand the layers
2. Review Domain layer code in `src/Domain/`
3. Study how aggregates, value objects, and events work
4. See [PROCESS_FLOWS.md](./PROCESS_FLOWS.md) for business flows

### For Learning Hexagonal Architecture

1. Read [ARCHITECTURE.md](./ARCHITECTURE.md) - Ports and Adapters section
2. Compare Domain interfaces (`InvoiceRepository`) with Infrastructure implementations
3. Understand how adapters can be swapped
4. Review [DESIGN_DECISIONS.md](./DESIGN_DECISIONS.md) for rationale

### For Learning CQRS

1. Study [ARCHITECTURE.md](./ARCHITECTURE.md) - CQRS section
2. Compare Command handlers vs Query handlers
3. See how commands modify state vs queries read data
4. Review Application layer structure

### For Learning Event-Driven Architecture

1. Read [PROCESS_FLOWS.md](./PROCESS_FLOWS.md) - Event Processing Flow
2. Study domain events in `src/Domain/Invoice/Event/`
3. See event bus implementation in Infrastructure
4. Review event listeners

### For API Development

1. Read [API_SPECIFICATION.md](./API_SPECIFICATION.md)
2. Review controllers in `src/Presentation/Controller/`
3. Test endpoints using Swagger UI at `/api/doc`
4. Study OpenAPI annotations

## Code Structure Guide

### Domain Layer (`src/Domain/`)

**Key Files:**
- `Invoice/Invoice.php` - Aggregate root
- `Invoice/ValueObject/` - Value objects
- `Invoice/Event/` - Domain events
- `Invoice/Repository/InvoiceRepository.php` - Port interface
- `Shared/` - Shared domain concepts

**Study Points:**
- How business logic is encapsulated
- Immutability of value objects
- Domain events for side effects
- Repository interface (port)

### Application Layer (`src/Application/`)

**Key Files:**
- `Command/` - Commands and handlers (writes)
- `Query/` - Queries and handlers (reads)
- `Query/ViewModel/` - DTOs for queries

**Study Points:**
- CQRS separation
- Command handlers orchestrate domain
- Query handlers transform data
- View models for API responses

### Infrastructure Layer (`src/Infrastructure/`)

**Key Files:**
- `Persistence/Doctrine/` - Database implementation
- `EventBus/` - RabbitMQ implementation
- `EventBus/EventListener/` - Event handlers

**Study Points:**
- How ports are implemented
- Doctrine entity mapping
- Event bus adapter
- Event listeners

### Presentation Layer (`src/Presentation/`)

**Key Files:**
- `Controller/InvoiceController.php` - REST endpoints

**Study Points:**
- OpenAPI annotations
- Request/response handling
- Command/Query dispatching

## Testing Guide

### Unit Tests (`tests/Unit/`)

- Test domain logic in isolation
- No dependencies on infrastructure
- Fast execution

### Acceptance Tests (`features/`, `tests/Behat/`)

- Test complete workflows
- BDD approach with Gherkin
- End-to-end scenarios

## Best Practices Demonstrated

1. **SOLID Principles**: Applied throughout
2. **Clean Code**: Readable, maintainable
3. **Test-Driven**: Tests for critical paths
4. **Documentation**: Comprehensive docs
5. **Type Safety**: Strict types, PHPStan
6. **Code Standards**: PSR-12 compliance

## Questions to Explore

1. How does the Domain layer remain independent?
2. How are commands different from queries?
3. How do domain events flow through the system?
4. How can you swap database implementations?
5. How does CQRS improve scalability?
6. How do value objects improve type safety?

## Next Steps

1. **Run the application**: Follow README.md setup
2. **Explore the code**: Start with Domain layer
3. **Run tests**: Understand test structure
4. **Modify code**: Add new features
5. **Study patterns**: Apply to other projects

## Additional Resources

- [Symfony Documentation](https://symfony.com/doc/8.0/index.html)
- [Domain-Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html)
- [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/)
- [CQRS Pattern](https://martinfowler.com/bliki/CQRS.html)
- [Event-Driven Architecture](https://martinfowler.com/articles/201701-event-driven.html)
