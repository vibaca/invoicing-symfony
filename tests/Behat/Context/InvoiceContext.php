<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use App\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Kernel;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class InvoiceContext implements Context
{
    private ?Response $response = null;
    private ?string $invoiceId = null;
    private ?string $invoiceNumber = null;
    private KernelInterface $kernel;
    private ?EntityManagerInterface $entityManager = null;
    private ?Connection $connection = null;

    public function __construct()
    {
        // CRITICAL: Set APP_ENV to 'test' FIRST, before any .env files are loaded
        // This ensures Symfony loads .env.test instead of .env
        putenv('APP_ENV=test');
        $_SERVER['APP_ENV'] = 'test';
        $_ENV['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '1';
        $_ENV['APP_DEBUG'] = '1';
        
        // Force DATABASE_URL to test database
        // This MUST be set before Kernel is created
        $testDatabaseUrl = 'postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8';
        putenv('DATABASE_URL=' . $testDatabaseUrl);
        $_ENV['DATABASE_URL'] = $testDatabaseUrl;
        $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
        
        // Load .env.test file explicitly
        $envTestPath = __DIR__ . '/../../.env.test';
        if (file_exists($envTestPath)) {
            $dotenv = new Dotenv();
            // Load .env.test - this will load .env.test and .env.test.local if exists
            $dotenv->loadEnv($envTestPath, 'APP_ENV', 'test');
        }
        
        // Force DATABASE_URL again after loading .env.test (override any .env.local values)
        putenv('DATABASE_URL=' . $testDatabaseUrl);
        $_ENV['DATABASE_URL'] = $testDatabaseUrl;
        $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
        
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        
        // Verify we're using the correct database after Kernel boot
        // This verification happens in setUpDatabase() before cleaning, so we don't need it here
    }

    /**
     * @BeforeSuite
     */
    public static function setUpTestDatabase(BeforeSuiteScope $scope): void
    {
        try {
            $testDbName = 'invoicing_test_db';
            $testDatabaseUrl = 'postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8';
            
            // Parse database URL to extract connection parameters
            $urlForParse = str_replace(['postgresql://', 'postgres://'], 'http://', $testDatabaseUrl);
            $urlParts = parse_url($urlForParse);
            
            if ($urlParts === false) {
                throw new \RuntimeException("Invalid DATABASE_URL format: {$testDatabaseUrl}");
            }
            
            // Extract connection parameters
            $host = $urlParts['host'] ?? 'postgres';
            $port = $urlParts['port'] ?? 5432;
            $user = $urlParts['user'] ?? 'invoicing_user';
            $password = $urlParts['pass'] ?? 'invoicing_pass';
            
            // STEP 1: Create the test database FIRST (before creating Kernel)
            // Connect to postgres database (not the test database) to create it
            $adminParams = [
                'driver' => 'pdo_pgsql',
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'password' => $password,
                'dbname' => 'postgres', // Connect to postgres database
            ];
            
            $adminConnection = DriverManager::getConnection($adminParams);
            
            // Check if database exists
            $result = $adminConnection->executeQuery(
                "SELECT 1 FROM pg_database WHERE datname = ?",
                [$testDbName]
            );
            
            if ($result->rowCount() === 0) {
                // Create test database
                $adminConnection->executeStatement("CREATE DATABASE {$testDbName}");
                echo "✓ Test database '{$testDbName}' created\n";
            } else {
                // Drop and recreate to ensure clean state
                // First, terminate all connections
                $adminConnection->executeStatement(
                    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()",
                    [$testDbName]
                );
                $adminConnection->executeStatement("DROP DATABASE IF EXISTS {$testDbName}");
                $adminConnection->executeStatement("CREATE DATABASE {$testDbName}");
                echo "✓ Test database '{$testDbName}' recreated\n";
            }
            
            $adminConnection->close();
            
            // STEP 2: Now set up environment and create Kernel with correct DATABASE_URL
            // CRITICAL: Set APP_ENV to 'test' FIRST, before any .env files are loaded
            putenv('APP_ENV=test');
            $_SERVER['APP_ENV'] = 'test';
            $_ENV['APP_ENV'] = 'test';
            $_SERVER['APP_DEBUG'] = '1';
            $_ENV['APP_DEBUG'] = '1';
            
            // Force DATABASE_URL to test database BEFORE creating Kernel
            putenv('DATABASE_URL=' . $testDatabaseUrl);
            $_ENV['DATABASE_URL'] = $testDatabaseUrl;
            $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
            
            // Load .env.test file if it exists
            $envTestPath = __DIR__ . '/../../.env.test';
            if (file_exists($envTestPath)) {
                $dotenv = new Dotenv();
                $dotenv->loadEnv($envTestPath, 'APP_ENV', 'test');
            }
            
            // Force DATABASE_URL again after loading .env.test (override any .env.local values)
            putenv('DATABASE_URL=' . $testDatabaseUrl);
            $_ENV['DATABASE_URL'] = $testDatabaseUrl;
            $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
            
            // STEP 3: Create Kernel and run migrations
            $kernel = new Kernel('test', true);
            $kernel->boot();
            
            // Verify Kernel is using the test database
            $container = $kernel->getContainer();
            $entityManager = $container->get('doctrine.orm.entity_manager');
            $connection = $entityManager->getConnection();
            $currentDatabase = $connection->executeQuery('SELECT current_database()')->fetchOne();
            
            if ($currentDatabase !== $testDbName) {
                throw new \RuntimeException(
                    sprintf(
                        'SAFETY ERROR: Kernel connected to database "%s" instead of test database "%s". ' .
                        'Check your .env.test configuration.',
                        $currentDatabase,
                        $testDbName
                    )
                );
            }
            
            // Run migrations on test database
            $application = new Application($kernel);
            $application->setAutoExit(false);
            
            $input = new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
                '--env' => 'test',
            ]);
            
            $output = new NullOutput();
            $exitCode = $application->run($input, $output);
            
            if ($exitCode !== 0) {
                throw new \RuntimeException("Migrations failed with exit code: {$exitCode}");
            }
            
            echo "✓ Test database migrations executed\n";
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $trace = $e->getTraceAsString();
            echo "✗ Error setting up test database: {$errorMessage}\n";
            echo "Trace:\n{$trace}\n";
            throw $e; // Re-throw to fail the suite
        }
    }

    /**
     * @AfterSuite
     */
    public static function tearDownTestDatabase(AfterSuiteScope $scope): void
    {
        try {
            // Load .env.test file if it exists
            $envTestPath = __DIR__ . '/../../.env.test';
            if (file_exists($envTestPath)) {
                $dotenv = new Dotenv();
                $dotenv->loadEnv($envTestPath);
            }
            
            // Set environment variables for test
            $_SERVER['APP_ENV'] = 'test';
            $_SERVER['APP_DEBUG'] = '1';
            
            // Get database URL from environment
            $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? 'postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8';
            
            // Parse database URL to extract connection parameters
            $urlForParse = str_replace(['postgresql://', 'postgres://'], 'http://', $databaseUrl);
            $urlParts = parse_url($urlForParse);
            
            if ($urlParts === false) {
                echo "⚠ Warning: Invalid DATABASE_URL format, skipping database drop\n";
                return;
            }
            
            $testDbName = 'invoicing_test_db';
            
            // Extract connection parameters
            $host = $urlParts['host'] ?? 'postgres';
            $port = $urlParts['port'] ?? 5432;
            $user = $urlParts['user'] ?? 'invoicing_user';
            $password = $urlParts['pass'] ?? 'invoicing_pass';
            
            // Connect to postgres database to drop test database
            $adminParams = [
                'driver' => 'pdo_pgsql',
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'password' => $password,
                'dbname' => 'postgres', // Connect to postgres database
            ];
            
            $adminConnection = DriverManager::getConnection($adminParams);
            
            // Terminate all connections to the test database
            $adminConnection->executeStatement(
                "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()",
                [$testDbName]
            );
            
            // Drop test database
            $adminConnection->executeStatement("DROP DATABASE IF EXISTS {$testDbName}");
            echo "✓ Test database '{$testDbName}' dropped\n";
            
            $adminConnection->close();
        } catch (\Exception $e) {
            echo "⚠ Warning: Could not drop test database: " . $e->getMessage() . "\n";
            // Don't throw - this is cleanup, failure is not critical
        }
    }

    /**
     * @BeforeScenario
     */
    public function setUpDatabase(BeforeScenarioScope $scope): void
    {
        // Ensure we're using test environment
        $_SERVER['APP_ENV'] = 'test';
        
        $container = $this->kernel->getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->connection = $this->entityManager->getConnection();
        
        // Verify we're connected to test database before cleaning
        $currentDatabase = $this->connection->executeQuery('SELECT current_database()')->fetchOne();
        if ($currentDatabase !== 'invoicing_test_db') {
            throw new \RuntimeException(
                sprintf(
                    'SAFETY ERROR: Connected to database "%s" instead of test database "invoicing_test_db". ' .
                    'Check your .env.test configuration.',
                    $currentDatabase
                )
            );
        }
        
        // Clean all tables before each scenario
        // Using TRUNCATE is more reliable than transactions with HTTP requests
        $this->cleanAllTables();
        
        // Reset context state
        $this->invoiceId = null;
        $this->invoiceNumber = null;
        $this->response = null;
    }

    /**
     * @AfterScenario
     */
    public function tearDownDatabase(AfterScenarioScope $scope): void
    {
        // Clean all tables after scenario to ensure clean state
        $this->cleanAllTables();
        
        // Clear entity manager
        if ($this->entityManager !== null) {
            $this->entityManager->clear();
        }
        
        // Reset context state
        $this->invoiceId = null;
        $this->invoiceNumber = null;
        $this->response = null;
    }

    /**
     * Clean all tables using TRUNCATE
     * SAFETY: Only works on test database (invoicing_test_db)
     */
    private function cleanAllTables(): void
    {
        if ($this->connection === null) {
            return;
        }

        // SAFETY CHECK: Verify we're using the test database
        $currentDatabase = $this->connection->executeQuery('SELECT current_database()')->fetchOne();
        
        if ($currentDatabase !== 'invoicing_test_db') {
            throw new \RuntimeException(
                sprintf(
                    'SAFETY ERROR: Attempted to clean database "%s" instead of test database. ' .
                    'This is a safety measure to prevent accidental data loss in the main database.',
                    $currentDatabase
                )
            );
        }

        try {
            // Truncate invoices table (CASCADE will handle any foreign key constraints)
            // TRUNCATE is faster than DELETE and resets auto-increment sequences
            $this->connection->executeStatement('TRUNCATE TABLE invoices CASCADE;');
        } catch (\Exception $e) {
            // If TRUNCATE fails (e.g., table doesn't exist or has active connections),
            // try DELETE as fallback
            try {
                $this->connection->executeStatement('DELETE FROM invoices;');
            } catch (\Exception $deleteException) {
                // Ignore - table might be empty or not exist yet
                // This can happen on first run before migrations
            }
        }
    }


    /**
     * @Given /^I send a POST request to "([^"]*)" with body:$/
     */
    public function iSendAPostRequestToWithBody(string $path, PyStringNode $body): void
    {
        if (strpos($path, ':invoiceId') !== false) {
            if (empty($this->invoiceId)) {
                throw new \RuntimeException('Invoice ID is not set. Create an invoice first.');
            }
            $path = str_replace(':invoiceId', $this->invoiceId, $path);
        }

        $request = Request::create($path, 'POST', [], [], [], [], $body->getRaw());
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        $this->response = $this->kernel->handle($request);

        if ($this->response->getStatusCode() === 201) {
            $content = json_decode($this->response->getContent(), true);
            if (isset($content['id'])) {
                $this->invoiceId = $content['id'];
            }
        }
    }

    /**
     * @When /^I send a GET request to "([^"]*)"$/
     */
    public function iSendAGetRequestTo(string $path): void
    {
        if (strpos($path, ':invoiceId') !== false) {
            if (empty($this->invoiceId)) {
                throw new \RuntimeException('Invoice ID is not set. Create an invoice first.');
            }
            $path = str_replace(':invoiceId', $this->invoiceId, $path);
        }

        $request = Request::create($path, 'GET');
        $request->headers->set('Accept', 'application/json');

        $this->response = $this->kernel->handle($request);
    }

    /**
     * @Given /^I send a POST request to "([^"]*)"$/
     */
    public function iSendAPostRequestTo(string $path): void
    {
        if (strpos($path, ':invoiceId') !== false) {
            if (empty($this->invoiceId)) {
                throw new \RuntimeException('Invoice ID is not set. Create an invoice first.');
            }
            $path = str_replace(':invoiceId', $this->invoiceId, $path);
        }

        $request = Request::create($path, 'POST');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        $this->response = $this->kernel->handle($request);
    }

    /**
     * @When /^I send a DELETE request to "([^"]*)"$/
     */
    public function iSendADeleteRequestTo(string $path): void
    {
        if (strpos($path, ':invoiceId') !== false) {
            if (empty($this->invoiceId)) {
                throw new \RuntimeException('Invoice ID is not set. Create an invoice first.');
            }
            $path = str_replace(':invoiceId', $this->invoiceId, $path);
        }

        $request = Request::create($path, 'DELETE');
        $request->headers->set('Accept', 'application/json');
        $this->response = $this->kernel->handle($request);
    }

    /**
     * @When /^I send a PATCH request to "([^"]*)" with body:$/
     */
    public function iSendAPatchRequestToWithBody(string $path, PyStringNode $body): void
    {
        if (strpos($path, ':invoiceId') !== false) {
            if (empty($this->invoiceId)) {
                throw new \RuntimeException('Invoice ID is not set. Create an invoice first.');
            }
            $path = str_replace(':invoiceId', $this->invoiceId, $path);
        }

        $request = Request::create($path, 'PATCH', [], [], [], [], $body->getRaw());
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');
        $this->response = $this->kernel->handle($request);
    }

    /**
     * @Given I have created an invoice with number :number
     */
    public function iHaveCreatedAnInvoiceWithNumber(string $number): void
    {
        // No need to make unique - transactions ensure clean state
        $body = json_encode([
            'invoiceNumber' => $number,
            'customerId' => '550e8400-e29b-41d4-a716-446655440000',
            'issueDate' => '2025-01-25',
            'dueDate' => '2025-02-25',
        ]);

        $request = Request::create('/api/invoices', 'POST', [], [], [], [], $body);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        $response = $this->kernel->handle($request);
        $content = json_decode($response->getContent(), true);

        if (! isset($content['id'])) {
            throw new \RuntimeException(
                sprintf('Failed to create invoice. Response: %s', $response->getContent())
            );
        }

        $this->invoiceId = $content['id'];
        $this->invoiceNumber = $number;
    }

    /**
     * @Given I have added an item to the invoice
     */
    public function iHaveAddedAnItemToTheInvoice(): void
    {
        $body = json_encode([
            'productId' => '550e8400-e29b-41d4-a716-446655440001',
            'description' => 'Test Product',
            'quantity' => 1,
            'unitPrice' => 100.0,
        ]);

        $path = '/api/invoices/' . $this->invoiceId . '/items';
        $request = Request::create($path, 'POST', [], [], [], [], $body);
        $request->headers->set('Content-Type', 'application/json');

        $this->kernel->handle($request);
    }

    /**
     * @Then the response status code should be :code
     */
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response available');
        }

        if ($this->response->getStatusCode() !== $code) {
            throw new \RuntimeException(
                sprintf(
                    'Expected status code %d, got %d. Response: %s',
                    $code,
                    $this->response->getStatusCode(),
                    $this->response->getContent()
                )
            );
        }
    }

    /**
     * @Then the response should contain :text
     */
    public function theResponseShouldContain(string $text): void
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response available');
        }

        $content = $this->response->getContent();
        if (strpos($content, $text) === false) {
            throw new \RuntimeException(
                sprintf('Response does not contain "%s". Response: %s', $text, $content)
            );
        }
    }
}
