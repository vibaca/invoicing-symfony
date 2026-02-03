.PHONY: help install up down build test test-unit test-behat phpstan phpcs phpcbf clean migrate db-reset env-create env-test-create reset verify worker-start worker-stop

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	@if [ ! -d vendor ]; then \
		echo "Installing dependencies from lock file..."; \
		docker-compose run --rm -e COMPOSER_PROCESS_TIMEOUT=2000 php composer install --no-interaction; \
	else \
		echo "Updating dependencies..."; \
		docker-compose run --rm php composer update --no-interaction --no-security-blocking; \
	fi

update: ## Update dependencies
	docker-compose run --rm php composer update --no-interaction --no-security-blocking

up: ## Start all containers
	docker-compose up -d

down: ## Stop all containers
	docker-compose down

down-volumes: ## Stop containers and remove volumes
	docker-compose down -v

build: ## Build Docker images
	docker-compose build

test: test-unit test-behat ## Run all tests

verify: phpstan phpcbf phpcs test ## Run static analysis, style auto-fix, checks, and tests
	@echo "Running PHPStan, PHP Code Beautifier (phpcbf), style checks (phpcs), then tests..."
	$(MAKE) phpstan
	$(MAKE) phpcbf || true
	$(MAKE) phpcs
	$(MAKE) test

TEST_DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8

test-unit: ## Run unit tests (use test DB; overrides container DATABASE_URL)
	docker-compose run --rm -e APP_ENV=test -e DATABASE_URL="$(TEST_DATABASE_URL)" -e MESSENGER_TRANSPORT_DSN=sync:// php vendor/bin/phpunit

test-behat: ## Run Behat acceptance tests (creates and drops test DB; forces test DB URL)
	docker-compose run --rm -e APP_ENV=test -e DATABASE_URL="$(TEST_DATABASE_URL)" -e MESSENGER_TRANSPORT_DSN=sync:// php vendor/bin/behat

phpstan: ## Run PHPStan static analysis
	docker-compose run --rm php vendor/bin/phpstan analyse src tests

phpcs: ## Run PHP CodeSniffer
	docker-compose run --rm php vendor/bin/phpcs

phpcbf: ## Fix code style issues
	docker-compose run --rm php vendor/bin/phpcbf

migrate: ## Run migrations
	@if [ ! -d vendor ]; then \
		echo "Installing dependencies..."; \
		docker-compose run --rm -e COMPOSER_PROCESS_TIMEOUT=2000 php composer install; \
	fi
	docker-compose run --rm php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Reinitialize development database (terminate connections, drop, create, migrate)
	@echo "Reinitializing development database..."
	-docker-compose exec -T postgres psql -U invoicing_user -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'invoicing_db' AND pid <> pg_backend_pid();" 2>/dev/null || true
	docker-compose exec -T postgres psql -U invoicing_user -d postgres -c "DROP DATABASE IF EXISTS invoicing_db;"
	docker-compose exec -T postgres psql -U invoicing_user -d postgres -c "CREATE DATABASE invoicing_db;"
	@echo "✓ Development database 'invoicing_db' recreated"
	$(MAKE) migrate
	@echo "✓ Migrations applied"

migrate-test: ## Run database migrations on test database
	docker-compose run --rm -e APP_ENV=test -e DATABASE_URL="$(TEST_DATABASE_URL)" -e MESSENGER_TRANSPORT_DSN=sync:// php bin/console doctrine:migrations:migrate --no-interaction --env=test

migrate-create: ## Create a new migration
	docker-compose run --rm php bin/console doctrine:migrations:generate

env-create: ## Create .env from .env.example/.env.test or create minimal .env if no template
	@sh ./scripts/env-create.sh


env-test-create: ## Create .env.test from .env.test.dist/.env.example or create minimal .env.test if no template
	@sh ./scripts/env-test-create.sh

worker-start: ## Start messenger worker inside running `php` container (detached)
	@echo "Starting messenger worker inside php container..."
	@if [ -z "$$(docker-compose ps -q php)" ]; then \
		echo "PHP container not running; starting containers first..."; \
		docker-compose up -d; \
	fi
	@docker-compose exec -d php php bin/console messenger:consume async -vv || echo "Failed to start worker (ensure php container is running)"

worker-stop: ## Stop messenger worker processes inside `php` container
	@echo "Stopping messenger worker processes in php container..."
	@docker-compose exec -T php sh -c "ps aux | grep '[m]essenger:consume' | awk '{print \$$1}' | xargs -I{} kill -9 {}" || true

test-db-create: ## Create test database
	docker-compose exec -T postgres psql -U invoicing_user -d postgres -c "CREATE DATABASE invoicing_test_db;" || echo "Database may already exist"

test-db-drop: ## Drop test database
	docker-compose exec -T postgres psql -U invoicing_user -d postgres -c "DROP DATABASE IF EXISTS invoicing_test_db;"

test-db-setup: test-db-create migrate-test ## Setup test database (create and migrate)

clean: ## Clean cache and logs
	docker-compose run --rm php bin/console cache:clear
	rm -rf var/cache/* var/log/*

shell: ## Open PHP container shell
	docker-compose exec php sh

logs: ## Show container logs
	docker-compose logs -f

status: ## Show container status
	docker-compose ps

check: ## Check if services are running
	@echo "Checking containers..."
	@docker-compose ps
	@echo "\nChecking PHP service..."
	@docker-compose exec -T php php -v || echo "PHP container not running"
	@echo "\nChecking Nginx..."
	@curl -s http://localhost:8080 > /dev/null && echo "Nginx is responding" || echo "Nginx is not responding"

setup: env-create env-test-create build install migrate up worker-start ## Complete setup (create env, build, install, migrate, start and start worker)

reset:
	@echo "🧹 Resetting project to fresh git clone state (project-scoped)..."
	# Remove local env files
	-rm -f .env .env.test
	rm -rf vendor/ var/
	## stop and remove only this compose project containers, images and volumes
		docker-compose -p invoicing-symfony down --rmi local -v --remove-orphans
	## Remove images created/labelled by this compose project (safe: only project images)
		docker image rm $$(docker images --filter "label=com.docker.compose.project=invoicing-symfony" -q) 2>/dev/null || true
	@echo "✅ Project reset. Run 'make setup' to install everything."
