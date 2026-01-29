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

#### Test Database Setup

The test database is **automatically created before tests and dropped after tests**:

```bash
make test-behat  # Automatically creates, migrates, and drops test DB
```

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
