#!/usr/bin/env sh
set -euo pipefail

# Create .env from .env.example/.env.test or create minimal .env if no template
if [ -f .env ]; then
  echo ".env already exists"
  exit 0
fi

if [ -f .env.example ]; then
  echo "Creating .env from .env.example"
  cp .env.example .env
  echo "✓ .env created - please edit .env to adjust local credentials"
  exit 0
fi

if [ -f .env.test ]; then
  echo "Creating .env from .env.test"
  cp .env.test .env
  echo "✓ .env created - please edit .env to adjust local credentials"
  exit 0
fi

# Fallback: create minimal .env
echo "No template (.env.example or .env.test) found — creating minimal .env"
echo 'APP_ENV=dev' > .env
echo 'APP_SECRET=change_me' >> .env
echo 'DATABASE_URL=postgresql://invoicing_user:invoicing_pass@postgres:5432/invoicing_db?serverVersion=16&charset=utf8' >> .env
echo "✓ minimal .env created (edit credentials)"
