.PHONY: help install up down build test test-unit test-behat phpstan phpcs phpcbf clean migrate db-reset env-create env-test-create reset

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	@if [ ! -d vendor ]; then \
		echo "Installing dependencies from lock file..."; \
		docker-compose run --rm php composer install --no-interaction; \
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

TEST_DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8

test-unit: ## Run unit tests (use test DB; overrides container DATABASE_URL)
	docker-compose run --rm -e APP_ENV=test -e DATABASE_URL="$(TEST_DATABASE_URL)" php vendor/bin/phpunit

test-behat: ## Run Behat acceptance tests (creates and drops test DB; forces test DB URL)
	docker-compose run --rm -e APP_ENV=test -e DATABASE_URL="$(TEST_DATABASE_URL)" php vendor/bin/behat

phpstan: ## Run PHPStan static analysis
	docker-compose run --rm php vendor/bin/phpstan analyse

phpcs: ## Run PHP CodeSniffer
	docker-compose run --rm php vendor/bin/phpcs

phpcbf: ## Fix code style issues
	docker-compose run --rm php vendor/bin/phpcbf

migrate: ## Run migrations
	@if [ ! -d vendor ]; then \
		echo "Installing dependencies..."; \
		docker-compose run --rm php composer install; \
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
	docker-compose run --rm -e APP_ENV=test -e DATABASE_URL="$(TEST_DATABASE_URL)" php bin/console doctrine:migrations:migrate --no-interaction --env=test

migrate-create: ## Create a new migration
	docker-compose run --rm php bin/console doctrine:migrations:generate

env-create: ## Create .env from .env.example/.env.test or create minimal .env if no template
	@if [ -f .env ]; then \
		echo ".env already exists"; \
	elif [ -f .env.example ]; then \
		echo "Creating .env from .env.example"; \
		cp .env.example .env; \
		echo "✓ .env created - please edit .env to adjust local credentials"; \
	elif [ -f .env.test ]; then \
		echo "Creating .env from .env.test"; \
		cp .env.test .env; \
		echo "✓ .env created - please edit .env to adjust local credentials"; \
	else \
		echo "No template (.env.example or .env.test) found — creating minimal .env"; \
		echo 'APP_ENV=dev' > .env; \
		echo 'APP_SECRET=change_me' >> .env; \
		echo 'DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_db?serverVersion=16&charset=utf8' >> .env; \
		echo "✓ minimal .env created (edit credentials)"; \
	fi

env-test-create: ## Create .env.test from .env.test.dist/.env.example or create minimal .env.test if no template
	@if [ -f .env.test ]; then \
		echo ".env.test already exists"; \
	elif [ -f .env.test.dist ]; then \
		echo "Creating .env.test from .env.test.dist"; \
		cp .env.test.dist .env.test; \
		echo "✓ .env.test created - please edit .env.test to adjust local credentials"; \
	elif [ -f .env.example ]; then \
		echo "Creating .env.test from .env.example (overriding DATABASE_URL + APP_ENV)"; \
		awk '/^DATABASE_URL=/ {print "DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8"; next} {print}' .env.example > .env.test; \
		awk '/^APP_ENV=/ {print "APP_ENV=test"; next} {print}' .env.test > .env.test.tmp && mv .env.test.tmp .env.test || true; \
		echo "✓ .env.test created - please edit .env.test to adjust local credentials"; \
	else \
		echo "No template (.env.test.dist or .env.example) found — creating minimal .env.test"; \
		echo 'APP_ENV=test' > .env.test; \
		echo 'APP_SECRET=change_me' >> .env.test; \
		echo 'DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8' >> .env.test; \
		echo 'MESSENGER_TRANSPORT_DSN=sync://' >> .env.test; \
		echo "✓ minimal .env.test created (edit credentials)"; \
	fi

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

setup: env-create build install migrate up ## Complete setup (create env, build, install, migrate, start)

reset:
	@echo "🧹 Resetting project to fresh git clone state (project-scoped)..."
	# Remove local env files
	-rm -f .env .env.test
	rm -rf vendor/ var/ composer.lock
	## stop and remove only this compose project containers, images and volumes
		docker-compose -p invoicing-symfony down --rmi local -v --remove-orphans
	## Remove images created/labelled by this compose project (safe: only project images)
		docker image rm $$(docker images --filter "label=com.docker.compose.project=invoicing-symfony" -q) 2>/dev/null || true
	@echo "✅ Project reset. Run 'make setup' to install everything."
