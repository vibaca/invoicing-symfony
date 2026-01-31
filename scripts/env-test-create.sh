#!/usr/bin/env sh
set -euo pipefail

# Create .env.test from .env.test.dist/.env.example or create minimal .env.test if no template
if [ -f .env.test ]; then
  echo ".env.test already exists"
  exit 0
fi

if [ -f .env.test.dist ]; then
  echo "Creating .env.test from .env.test.dist"
  cp .env.test.dist .env.test
  echo "✓ .env.test created - please edit .env.test to adjust local credentials"
  exit 0
fi

if [ -f .env.example ]; then
  echo "Creating .env.test from .env.example (appending test values)"
  cp .env.example .env.test
  echo 'APP_ENV=test' >> .env.test
  echo 'DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8' >> .env.test
  # Ensure MESSENGER_TRANSPORT_DSN is present for tests (fallback to sync transport)
  grep -q '^MESSENGER_TRANSPORT_DSN=' .env.test || echo 'MESSENGER_TRANSPORT_DSN=sync://' >> .env.test
  echo "✓ .env.test created - please edit .env.test to adjust local credentials"
  exit 0
fi

# Fallback: create minimal .env.test
echo "No template (.env.test.dist or .env.example) found — creating minimal .env.test"
echo 'APP_ENV=test' > .env.test
echo 'APP_SECRET=change_me' >> .env.test
echo 'DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_test_db?serverVersion=16&charset=utf8' >> .env.test
echo 'MESSENGER_TRANSPORT_DSN=sync://' >> .env.test
echo "✓ minimal .env.test created (edit credentials)"
