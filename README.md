# Invoicing System

A comprehensive invoicing backend system built with Symfony 7, implementing Domain-Driven Design (DDD), Hexagonal Architecture, CQRS (Command Query Responsibility Segregation), and Event-Driven Architecture.

## Architecture Overview

This project follows several architectural patterns and principles:

- **Domain-Driven Design (DDD)**: Business logic is encapsulated in the Domain layer
- **Hexagonal Architecture**: Clear separation between Domain, Application, Infrastructure, and Presentation layers
- **CQRS**: Separate commands (write operations) from queries (read operations)
- **Event-Driven Architecture**: Domain events are published via RabbitMQ
- **SOLID Principles**: Applied throughout the codebase

## Technology Stack

- **Framework**: Symfony 7.3
- **PHP**: 8.3
- **Database**: PostgreSQL 16
- **Message Broker**: RabbitMQ 3
- **Testing**: PHPUnit (unit tests), Behat (acceptance tests)
- **Static Analysis**: PHPStan, PHP CodeSniffer
- **API Documentation**: OpenAPI/Swagger (Nelmio API Doc Bundle)
- **Containerization**: Docker & Docker Compose

## Project Structure

```
invoicing-symfony/
├── config/                 # Symfony configuration
├── docker/                  # Docker configuration files
├── documentation/           # Architecture and process documentation
├── features/               # Behat feature files
├── migrations/             # Database migrations
├── public/                 # Web server entry point
├── src/
│   ├── Application/        # Application layer (Use Cases, CQRS)
│   │   ├── Command/        # Commands and handlers
│   │   └── Query/          # Queries and handlers
│   ├── Domain/             # Domain layer (Business logic)
│   │   ├── Invoice/        # Invoice aggregate
│   │   └── Shared/         # Shared domain concepts
│   ├── Infrastructure/    # Infrastructure layer (Adapters)
│   │   ├── EventBus/       # Event bus implementation
│   │   └── Persistence/    # Database persistence
│   └── Presentation/       # Presentation layer (REST API)
│       └── Controller/     # API controllers
└── tests/                  # Test files
    ├── Behat/             # Behat contexts
    └── Unit/              # Unit tests
```

## Prerequisites

- Docker 20.10+
- Docker Compose 2.0+
- Make (optional, for convenience commands)

## Quick Start

### 1. Clone and Setup

```bash
# Clone the repository
git clone <repository-url>
cd invoicing-symfony

# Complete setup (builds images, installs dependencies, runs migrations, starts containers)
make setup
```

### 2. Access the Application

- **API**: http://localhost:8080/api
- **API Documentation (Swagger)**: http://localhost:8080/api/doc
- **RabbitMQ Management**: http://localhost:15672 (user: `rabbitmq_user`, pass: `rabbitmq_pass`)
- **PostgreSQL**: localhost:5432 (user: `invoicing_user`, pass: `invoicing_pass`, db: `invoicing_db`)

## Available Make Commands

```bash
make help          # Show all available commands
make install        # Install PHP dependencies
make update         # Update PHP dependencies
make up             # Start all containers
make down           # Stop all containers
make down-volumes   # Stop containers and remove volumes
make build          # Build Docker images
make test           # Run all tests (unit + acceptance)
make test-unit      # Run unit tests only
make test-behat     # Run Behat acceptance tests
make phpstan        # Run PHPStan static analysis
make phpcs          # Run PHP CodeSniffer
make phpcbf         # Fix code style issues automatically
make migrate        # Run database migrations
make migrate-create # Create a new migration
make db-reset       # Reinitialize development DB (drop, create, migrate)
make clean          # Clear cache and logs
make shell          # Open PHP container shell
make logs           # Show container logs
make status         # Show container status
make check          # Check if services are running
```

### Reinitialize development database

To **reset** the development database (`invoicing_db`): terminate connections, drop it, create it, and run migrations:

```bash
make db-reset
```

Requires PostgreSQL to be running (`make up` or `docker-compose up -d`). Use this when you need a clean development database (e.g. after schema changes or to wipe data).

## Manual Setup (without Make)

### 1. Start Docker Containers

```bash
docker-compose up -d
```

### 2. Install Dependencies

```bash
docker-compose run --rm php composer update --no-interaction --no-security-blocking --no-scripts
```

**Note**: We use `--no-scripts` to avoid Symfony Flex issues during initial installation. Flex is temporarily disabled in `composer.json` but can be re-enabled if needed.

### 3. Run Database Migrations

```bash
docker-compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Verify Setup

```bash
# Check containers are running
docker-compose ps

# Check API is accessible
curl http://localhost:8080/api/doc
```

## API Endpoints

All endpoints are prefixed with `/api`. The Swagger UI documentation is available at `/api/doc`.

### Create Invoice
```bash
POST /api/invoices
Content-Type: application/json

{
  "invoiceNumber": "INV-2025-001",
  "customerId": "550e8400-e29b-41d4-a716-446655440000",
  "issueDate": "2025-01-25",
  "dueDate": "2025-02-25"
}
```

**Response**: `201 Created`
```json
{
  "id": "ac1a5519-4a7d-450b-b53d-328b28a6d65b",
  "message": "Invoice created successfully"
}
```

### Get Invoice
```bash
GET /api/invoices/{id}
```

**Response**: `200 OK`
```json
{
  "id": "ac1a5519-4a7d-450b-b53d-328b28a6d65b",
  "number": "INV-2025-001",
  "customerId": "550e8400-e29b-41d4-a716-446655440000",
  "status": "draft",
  "issueDate": "2025-01-25",
  "dueDate": "2025-02-25",
  "totalAmount": 199.98,
  "currency": "USD",
  "items": [...],
  "createdAt": "2025-01-25 18:26:13",
  "updatedAt": "2025-01-25 18:26:13"
}
```

### List Invoices
```bash
GET /api/invoices?page=1&limit=10
```

**Response**: `200 OK`
```json
{
  "invoices": [...],
  "total": 1,
  "page": 1,
  "limit": 10,
  "totalPages": 1
}
```

### Add Item to Invoice
```bash
POST /api/invoices/{id}/items
Content-Type: application/json

{
  "productId": "550e8400-e29b-41d4-a716-446655440001",
  "description": "Product description",
  "quantity": 2,
  "unitPrice": 99.99
}
```

**Response**: `200 OK`
```json
{
  "message": "Item added successfully"
}
```

### Remove Item from Invoice (draft only)
```bash
DELETE /api/invoices/{id}/items/{itemIndex}
```

**Response**: `200 OK`
```json
{
  "message": "Item removed successfully"
}
```

### Update Item Quantity (draft only)
```bash
PATCH /api/invoices/{id}/items/{itemIndex}
Content-Type: application/json

{"quantity": 5}
```

**Response**: `200 OK`
```json
{
  "message": "Item quantity updated successfully"
}
```

### Issue Invoice
```bash
POST /api/invoices/{id}/issue
```

**Response**: `200 OK`
```json
{
  "message": "Invoice issued successfully"
}
```

## Testing

The project uses a comprehensive testing strategy with complete data isolation. See [TESTING.md](documentation/TESTING.md) for detailed information.

### Test Database Isolation

Tests use a **separate database** (`invoicing_test_db`) that is:
- Automatically created before test execution
- Automatically dropped after test execution
- Completely isolated from development data
- Protected by safety checks to prevent accidental operations

When you run `make test-unit` or `make test-behat`, we **override** `DATABASE_URL` via `-e` so the test container connects only to `invoicing_test_db`. The running app (e.g. `make up`) uses `invoicing_db`; you will **never** see test data in the application.

### Unit Tests

```bash
make test-unit
```

### Acceptance Tests (Behat)

```bash
make test-behat
```

**What happens automatically:**
1. Test database is created (or recreated if exists)
2. Migrations are executed on test database
3. Each scenario runs with clean database state
4. Test database is dropped after all tests complete

### All Tests

```bash
make test
```

This runs both unit tests and acceptance tests in sequence.

## Code Quality

### PHPStan (Static Analysis)

```bash
make phpstan
# or
docker-compose run --rm php vendor/bin/phpstan analyse
```

### PHP CodeSniffer

```bash
# Check code style
make phpcs

# Fix code style automatically
make phpcbf
```

## Development Workflow

1. **Create a feature branch**
2. **Write tests first** (TDD approach)
3. **Implement the feature**
4. **Run tests**: `make test`
5. **Check code quality**: `make phpstan && make phpcs`
6. **Fix style issues**: `make phpcbf`
7. **Commit and push**

## Architecture Layers

### Domain Layer (`src/Domain/`)
Contains business logic, entities, value objects, and domain events. This layer has no dependencies on other layers.

### Application Layer (`src/Application/`)
Contains use cases, commands, queries, and their handlers. Orchestrates domain objects to fulfill use cases.

### Infrastructure Layer (`src/Infrastructure/`)
Contains implementations of ports defined in the domain (repositories, event bus, external services).

### Presentation Layer (`src/Presentation/`)
Contains REST API controllers and request/response handling.

## Event-Driven Architecture

Domain events are published via RabbitMQ when domain operations occur:

- `invoice.created`: Published when a new invoice is created
- `invoice.item_added`: Published when an item is added to an invoice

Event listeners are configured in `src/Infrastructure/EventBus/EventListener/`.

## Database Migrations

```bash
# Create a new migration
make migrate-create

# Run migrations
make migrate
```

## Troubleshooting

### Containers won't start
```bash
# Check logs
make logs

# Rebuild containers
make down
make build
make up
```

### Database connection issues
```bash
# Verify PostgreSQL is running
docker-compose ps postgres

# Check database connection
docker-compose exec php bin/console doctrine:query:sql "SELECT 1"

# Reinitialize development database (drop, create, migrate)
make db-reset

# If database doesn't exist, recreate volumes
make down-volumes
make up
make migrate
```

### RabbitMQ connection issues
```bash
# Verify RabbitMQ is running
docker-compose ps rabbitmq

# Access RabbitMQ management UI
# http://localhost:15672 (user: rabbitmq_user, pass: rabbitmq_pass)
```

### Composer/Flex issues
```bash
# If you encounter Flex errors, install with --no-scripts
docker-compose exec php composer update --no-interaction --no-security-blocking --no-scripts

# Or disable Flex temporarily in composer.json
# Set "symfony/flex": false in allow-plugins
```

### Clear cache
```bash
make clean
# Or manually
docker-compose exec php rm -rf var/cache/*
docker-compose exec php bin/console cache:clear
```

### API Documentation not showing operations
```bash
# Verify zircote/swagger-php is installed
docker-compose exec php composer show zircote/swagger-php

# Clear cache
docker-compose exec php bin/console cache:clear

# Check routes
docker-compose exec php bin/console debug:router | grep api
```

## Important Notes

### Symfony Flex
Symfony Flex is currently disabled in `composer.json` to avoid installation issues. All configuration files have been created manually. If you need to re-enable Flex, change `"symfony/flex": false` to `"symfony/flex": true` in `composer.json`.

### Dependencies
- **Symfony 7.3**: Latest stable version with security patches
- **Behat**: Using `friends-of-behat` packages (compatible with Symfony 7)
- **Swagger/OpenAPI**: Requires `zircote/swagger-php` for attribute processing
- **Twig & Asset**: Required for Swagger UI rendering

### Database
The database schema is managed through Doctrine Migrations. Always run migrations after pulling changes:
```bash
make migrate
```

To **reinitialize** the development database (drop, create, migrate):
```bash
make db-reset
```

## Documentation

Comprehensive documentation is available in the `documentation/` folder:

- **ARCHITECTURE.md**: Complete architecture documentation
- **PROCESS_FLOWS.md**: Business process flows and diagrams
- **DESIGN_DECISIONS.md**: Rationale for architectural decisions
- **API_SPECIFICATION.md**: Complete API reference
- **TESTING.md**: Testing strategy, data isolation, and best practices
- **README.md** (this file): Setup and usage instructions

## Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Ensure all tests pass
4. Run static analysis tools
5. Update documentation as needed

## License

MIT License

## Support

For questions or issues, please refer to the documentation in the `documentation/` folder or create an issue in the repository.
