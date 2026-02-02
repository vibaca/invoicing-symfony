# Testing Strategy

## Overview

This document explains the testing strategy for the invoicing system, focusing on how we ensure test data isolation and prevent conflicts between test runs.

## Problem Statement

Previously, tests could fail due to:
- Data persisting between test runs
- Conflicts when using fixed invoice numbers
- Tests affecting each other when run in sequence
- Mixing test data with development data

## Solution: Database Isolation with TRUNCATE

We've implemented a comprehensive solution that ensures complete data isolation:

### 1. Separate Test Database

- **Development Database**: `invoicing_db` (port 5432)
- **Test Database**: `invoicing_test_db` (port 5432, separate database)

This ensures that:
- Test data never mixes with development data
- Tests can be run safely without affecting development environment
- Multiple developers can run tests simultaneously without conflicts
- **Safety checks prevent accidental operations on the main database**

### 2. TRUNCATE-Based Test Isolation

Each Behat scenario runs with explicit data cleanup using `TRUNCATE`:

```php
@BeforeScenario
- Verify connection to test database (safety check)
- TRUNCATE all tables (clean state)
- Reset context state

@AfterScenario  
- TRUNCATE all tables (ensure clean state)
- Clear entity manager
- Reset context state
```

**Why TRUNCATE instead of Transactions?**
- Symfony's HTTP kernel manages its own database connections
- HTTP requests may bypass transaction boundaries
- `TRUNCATE` provides explicit, immediate data removal
- More reliable for acceptance tests with HTTP requests

**Benefits:**
- Each test starts with a clean database state
- No data persists between scenarios
- Tests are completely isolated from each other
- Explicit data cleanup ensures reliability
- Safety checks prevent accidental data loss

### 3. Configuration

#### Environment Configuration (`.env.test`)

```env
DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8
```

Note: The repository's `.env.example` may contain a RabbitMQ DSN for development (so running the app after `make setup` may default to using RabbitMQ). Tests, however, run with `MESSENGER_TRANSPORT_DSN=sync://` via the `.env.test` template or Makefile overrides to avoid relying on an external broker.

Developer note about the worker:

- Asynchronous messages published to the `async` transport are processed by a Messenger consumer (the "worker"). This project provides Make targets to manage the worker from your development environment:

```bash
make worker-start   # Start the messenger consumer in background (container: invoicing-worker)
make worker-stop    # Stop and remove the background worker container
```

- `make setup` runs `make worker-start` after starting containers, so a fresh clone will have the worker started automatically. If you prefer not to run a background container, run the consumer ad-hoc:

```bash
docker-compose run --rm php php bin/console messenger:consume async -vv
# or locally
php bin/console messenger:consume async -vv
```

- Tests remain isolated and deterministic because `.env.test` and the Makefile test targets force `MESSENGER_TRANSPORT_DSN=sync://` so they run inline and do not require RabbitMQ.

#### Test Database Setup

The test database is **automatically created before tests and dropped after tests**:

```bash
make test-behat  # Automatically creates, migrates, and drops test DB
```

### Creating `.env.test`

While tests force `APP_ENV=test` and `DATABASE_URL` can be provided via the environment, it's convenient to have a local `.env.test` file. Use the committed test template and copy it locally:

```bash
cp .env.test.dist .env.test
# or, if you prefer to create from the general example:
cp .env.example .env.test
```

`.env.test` is ignored by Git and intended only for local/CI usage. In CI, prefer injecting secrets via the runner and avoid committing sensitive values.

Automation and helper scripts

- The repository includes `scripts/env-create.sh` and `scripts/env-test-create.sh` to create `.env` and `.env.test` from templates in a robust, cross-platform way. These scripts are invoked by the `Makefile` targets `env-create` and `env-test-create` respectively.
- `make setup` will now call `env-create` and `env-test-create` so a fresh clone can run `make setup` and then `make test` without additional manual steps.
- The test env script ensures `MESSENGER_TRANSPORT_DSN=sync://` is present in `.env.test` by default to run tests without an external message broker.


#### Guarantee: Tests Never Touch Development Data

When you run `make test-unit` or `make test-behat`, we **override** the container environment so tests use **only** the test database:

- `docker-compose run -e APP_ENV=test -e DATABASE_URL=...invoicing_test_db... php vendor/bin/phpunit` (or `behat`)

The PHP container normally uses `invoicing_db` (dev) from docker-compose. The **test** run uses a new container with explicit `-e` overrides, so it connects to `invoicing_test_db`. The running application (e.g. `make up`) keeps using `invoicing_db`; you will **never** see test data in the app.

**Automatic Lifecycle:**
- `@BeforeSuite`: Creates test database and runs migrations
- `@AfterSuite`: Drops test database completely

**Manual Management (optional):**

```bash
make test-db-create    # Create test database manually
make migrate-test      # Run migrations on test database
make test-db-setup     # Both operations
make test-db-drop      # Drop test database manually
```

## Test Data Management

### Fixed Test Data

With TRUNCATE-based isolation, we can now use **fixed, predictable test data**:

```gherkin
Scenario: Create a new invoice
  Given I send a POST request to "/api/invoices" with body:
    """
    {
      "invoiceNumber": "INV-TEST-001",
      ...
    }
    """
```

**Why this works:**
- Each scenario starts with a clean database (TRUNCATE before)
- All data is removed after each scenario (TRUNCATE after)
- Next scenario starts with a clean database
- No conflicts with previous test data
- Fixed test data is safe and readable

### No Need for Unique Identifiers

Previously, we had to generate unique invoice numbers to avoid conflicts:

```php
// OLD - Not needed anymore
$uniqueNumber = $number . '-' . time() . '-' . substr(uniqid(), -6);
```

Now we can use simple, readable test data:

```php
// NEW - Clean and readable
'invoiceNumber' => $number
```

**Why this works:**
- Database is cleaned before each scenario
- No data persists between scenarios
- Fixed test data is safe and makes tests more readable

## Running Tests

### All Tests

```bash
make test
```

### Verify (style + tests)

You can run a single command that attempts to auto-fix style problems, runs style checks, and then executes the full test suite:

```bash
make verify
```

What it does:
- `make phpstan` — run static analysis (PHPStan)
- `make phpcbf` — attempts to auto-fix style issues (non-fatal)
- `make phpcs`  — runs CodeSniffer to report remaining style issues
- `make test`   — runs unit + acceptance tests

Use this when you want a single command to validate code quality and correctness before committing.


### Only Acceptance Tests (Behat)

```bash
make test-behat
```

This command will:
1. **@BeforeSuite**: Create test database (drop if exists, then create fresh)
2. **@BeforeSuite**: Run migrations on test database
3. **@BeforeSuite**: Verify Kernel is connected to test database (safety check)
4. Execute Behat scenarios (each with TRUNCATE before/after)
5. **@AfterSuite**: Drop test database completely

**Result**: Clean environment every time, no leftover test data

**Safety Features:**
- Database connection is verified before any operations
- `TRUNCATE` operations only work on `invoicing_test_db`
- Runtime exceptions prevent accidental operations on main database
- Environment variables are explicitly set to ensure correct database

### Manual Test Database Management

```bash
# Create test database
make test-db-create

# Drop test database (clean slate)
make test-db-drop

# Setup test database (create + migrate)
make test-db-setup

# Run migrations on test database
make migrate-test
```

### Development database reinitialize

To **reset** the development database (`invoicing_db`): terminate connections, drop it, create it, and run migrations:

```bash
make db-reset
```

Use this when you need a clean development database (e.g. after schema changes or to wipe all data). Requires PostgreSQL to be running (`make up`). This affects **only** `invoicing_db`; the test database (`invoicing_test_db`) is unchanged.

## Test Execution Flow

```
Suite Start
   │
   ▼
@BeforeSuite Hook
   ├─ Set APP_ENV=test and DATABASE_URL (force test database)
   ├─ Create test database (drop if exists, then create fresh)
   ├─ Create Kernel with test environment
   ├─ Verify Kernel connected to test database (safety check)
   ├─ Run migrations on test database
   └─ Database ready for tests
   │
   ▼
For Each Scenario:
   │
   ├─ @BeforeScenario Hook
   │   ├─ Verify connection to test database (safety check)
   │   ├─ TRUNCATE all tables (clean state)
   │   └─ Reset context state
   │
   ├─ Scenario Execution
   │   ├─ Create test data
   │   ├─ Execute test steps (HTTP requests)
   │   └─ Verify results
   │
   └─ @AfterScenario Hook
       ├─ TRUNCATE all tables (ensure clean state)
       ├─ Clear entity manager
       └─ Reset context state
   │
   ▼
Suite End
   │
   ▼
@AfterSuite Hook
   ├─ Terminate all connections to test database
   └─ Drop test database completely
```

## Benefits

### 1. **Data Isolation**
- Tests never interfere with each other
- No conflicts with fixed test data
- Predictable test execution
- Complete separation from development data

### 2. **Performance**
- TRUNCATE is fast and efficient
- No need to clean up data manually
- Parallel test execution possible
- Automatic database lifecycle management

### 3. **Reliability**
- Tests are deterministic
- Same test data produces same results
- No flaky tests due to data conflicts
- Explicit data cleanup ensures consistency

### 4. **Maintainability**
- Simple, readable test data
- No complex unique ID generation
- Easy to understand test scenarios
- Clear test execution flow

### 5. **Safety**
- Development data is never affected
- Test database is separate
- Runtime safety checks prevent accidental operations
- Can run tests safely in any environment
- Automatic verification of database connections

## Troubleshooting

### Test Database Doesn't Exist

```bash
make test-db-setup
```

### Tests Failing Due to Data Conflicts

This should not happen with TRUNCATE-based isolation. If it does:

1. Check that TRUNCATE is working:
   ```bash
   docker-compose exec postgres psql -U invoicing_user -d invoicing_test_db -c "SELECT COUNT(*) FROM invoices;"
   ```

2. Verify `.env.test` points to test database:
   ```bash
   cat .env.test | grep DATABASE_URL
   ```
   Should show: `DATABASE_URL=postgresql://...@postgres:5432/invoicing_test_db...`

3. Ensure `@BeforeScenario` and `@AfterScenario` hooks are working

4. Check for safety errors in test output - they indicate connection to wrong database

### Reset Test Database

```bash
make test-db-drop
make test-db-setup
```

## Best Practices

1. **Use Fixed Test Data**: With transaction isolation, fixed data is safe and readable
2. **Keep Scenarios Independent**: Each scenario should be self-contained
3. **Don't Rely on Execution Order**: Scenarios should work in any order
4. **Clean Up in Hooks**: Use `@BeforeScenario` and `@AfterScenario` for setup/teardown
5. **Separate Concerns**: Keep test data separate from development data

## Architecture

```
┌─────────────────────────────────────────┐
│         Test Execution                  │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      @BeforeScenario Hook               │
│  • Verify test database connection      │
│  • TRUNCATE all tables                  │
│  • Reset context state                  │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      Scenario Execution                 │
│  • Create test data                     │
│  • Execute test steps (HTTP requests)   │
│  • Verify results                       │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      @AfterScenario Hook                │
│  • TRUNCATE all tables                  │
│  • Clear Entity Manager                 │
│  • Reset context state                  │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      Next Scenario                      │
│  (Starts with clean database)           │
└─────────────────────────────────────────┘
```

## Safety Mechanisms

### Database Connection Verification

Before any database operations, the system verifies the connection:

```php
// Safety check in @BeforeScenario
$currentDatabase = $connection->executeQuery('SELECT current_database()')->fetchOne();
if ($currentDatabase !== 'invoicing_test_db') {
    throw new RuntimeException('SAFETY ERROR: Connected to wrong database');
}
```

### Environment Variable Enforcement

The system explicitly sets environment variables to ensure correct database:

```php
// Force test database URL
putenv('DATABASE_URL=' . $testDatabaseUrl);
$_ENV['DATABASE_URL'] = $testDatabaseUrl;
$_SERVER['DATABASE_URL'] = $testDatabaseUrl;
```

### TRUNCATE Safety

TRUNCATE operations include safety checks:

```php
// Only TRUNCATE if connected to test database
if ($currentDatabase !== 'invoicing_test_db') {
    throw new RuntimeException('SAFETY ERROR: Cannot TRUNCATE non-test database');
}
```

## Summary

The testing strategy ensures:
- ✅ Complete data isolation between tests
- ✅ No conflicts with fixed test data
- ✅ Separation of test and development data
- ✅ Fast and reliable test execution
- ✅ Simple and maintainable test code
- ✅ **Automatic database lifecycle management** (create before, drop after)
- ✅ **No manual database setup required**
- ✅ **Clean environment on every test run**
- ✅ **Runtime safety checks** prevent accidental operations on main database
- ✅ **Explicit environment variable management** ensures correct database connection
- ✅ **TRUNCATE-based cleanup** provides reliable data isolation for HTTP-based tests
