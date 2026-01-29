# Troubleshooting Guide

## Problem: Cannot access http://localhost:8080/api/doc

### Step 1: Check if containers are running

```bash
make status
# or
docker-compose ps
```

All containers should be in "Up" state:
- `invoicing_postgres` - Up
- `invoicing_rabbitmq` - Up  
- `invoicing_php` - Up
- `invoicing_nginx` - Up

### Step 2: Start containers if not running

```bash
make up
# or
docker-compose up -d
```

### Step 3: Check container logs

```bash
make logs
# or
docker-compose logs nginx
docker-compose logs php
```

### Step 4: Verify PHP service is working

```bash
docker-compose exec php php -v
docker-compose exec php bin/console list
```

### Step 5: Check if Nginx can reach PHP

```bash
docker-compose exec nginx wget -O- http://php:9000 || echo "Cannot reach PHP-FPM"
```

### Step 6: Test the API endpoint directly

```bash
# From host
curl http://localhost:8080/api/doc

# From inside PHP container
docker-compose exec php curl http://nginx/api/doc
```

### Step 7: Check file permissions

```bash
docker-compose exec php ls -la /var/www/html/public
docker-compose exec php ls -la /var/www/html/vendor
```

### Step 8: Rebuild containers if needed

```bash
make down
make build
make up
```

### Step 9: Clear Symfony cache

```bash
docker-compose exec php bin/console cache:clear
```

### Step 10: Check routes

```bash
docker-compose exec php bin/console debug:router
```

You should see `/api/doc` in the list.

## Common Issues

### Issue: "502 Bad Gateway"
- PHP-FPM is not running or not accessible
- Check: `docker-compose logs php`
- Solution: Restart PHP container: `docker-compose restart php`

### Issue: "404 Not Found"
- Routes are not registered
- Check: `docker-compose exec php bin/console debug:router`
- Solution: Clear cache and check route configuration

### Issue: "500 Internal Server Error"
- Application error
- Check: `docker-compose logs php`
- Check: `docker-compose exec php bin/console debug:container`

### Issue: Containers won't start
- Port conflicts (8080, 5432, 5672, 15672 already in use)
- Check: `lsof -i :8080` or `netstat -an | grep 8080`
- Solution: Stop conflicting services or change ports in docker-compose.yml

## Quick Fix Commands

```bash
# Complete restart
make down && make up

# Rebuild everything
make down && make build && make up

# Check everything
make check
```
