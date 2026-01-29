# Changelog

All notable changes to the Invoicing System project.

## [1.0.0] - 2025-01-25

### Initial Release

#### Added
- Complete invoicing system with DDD, Hexagonal Architecture, CQRS, and Event-Driven Architecture
- Symfony 7.3 framework
- PostgreSQL 16 database
- RabbitMQ 3 for event-driven architecture
- REST API with OpenAPI/Swagger documentation
- Docker and Docker Compose setup
- Unit tests with PHPUnit
- Acceptance tests with Behat
- Static analysis with PHPStan and PHP CodeSniffer
- Comprehensive documentation

#### Architecture
- Domain layer with Invoice aggregate
- Application layer with CQRS (Commands and Queries)
- Infrastructure layer with Doctrine and RabbitMQ adapters
- Presentation layer with REST API controllers

#### Features
- Create invoices
- Add items to invoices
- Issue invoices
- List and query invoices
- Domain events via RabbitMQ

#### Technical Details
- PHP 8.3
- Symfony 7.3
- PostgreSQL 16
- RabbitMQ 3
- Nelmio API Doc Bundle 4.38.7
- Friends of Behat extensions (Symfony 7 compatible)
- zircote/swagger-php for OpenAPI attributes

#### Configuration
- Symfony Flex temporarily disabled to avoid installation issues
- All configuration files created manually
- Docker Compose without version (using latest format)
- Doctrine Migrations configured
- Messenger configured for RabbitMQ

#### Known Issues
- Swagger UI shows "No operations defined" - OpenAPI attributes are configured but may need additional setup
- Symfony Flex disabled - can be re-enabled if needed

#### Documentation
- Architecture documentation
- Process flows documentation
- Design decisions documentation
- API specification
- Troubleshooting guide
