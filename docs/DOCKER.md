# Docker Deployment Guide

## Quick Start

### Build and Release

```bash
# Make the release script executable
chmod +x release.sh

# Build and push to Docker Hub with version tag
./release.sh v1.0.0

# Or build and push as latest
./release.sh
```

## Docker Image

The image is built from `serversideup/php:8.4-fpm-nginx` and includes:

- PHP 8.4 with FPM and Nginx
- Required extensions (intl, etc.)
- Production-optimized Composer dependencies
- SBOM and provenance attestations for security

## Running the Application

### Using Docker Compose (Recommended)

```bash
# Create .env file with required variables
cp .env.example .env

# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### Manual Docker Commands

**Web Server:**

```bash
docker run -d \
  --name breakster-web \
  -p 80:80 \
  -e APP_KEY=your-app-key \
  -e DB_HOST=your-db-host \
  -e DB_DATABASE=your-db-name \
  -e DB_USERNAME=your-db-user \
  -e DB_PASSWORD=your-db-password \
  itrcglobe/breakster:latest
```

**Queue Worker:**

```bash
docker run -d \
  --name breakster-queue \
  -e APP_KEY=your-app-key \
  -e DB_HOST=your-db-host \
  -e DB_DATABASE=your-db-name \
  -e DB_USERNAME=your-db-user \
  -e DB_PASSWORD=your-db-password \
  itrcglobe/breakster:latest \
  php artisan queue:work --verbose --tries=3 --timeout=90
```

**Laravel Reverb (WebSockets):**

```bash
docker run -d \
  --name breakster-reverb \
  -p 8080:8080 \
  -e APP_KEY=your-app-key \
  -e REVERB_APP_ID=your-app-id \
  -e REVERB_APP_KEY=your-app-key \
  -e REVERB_APP_SECRET=your-app-secret \
  itrcglobe/breakster:latest \
  php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Environment Variables

### Required Variables

- `APP_KEY` - Laravel application key
- `DB_HOST` - Database hostname
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password

### Optional Variables

- `APP_ENV` - Application environment (default: production)
- `APP_DEBUG` - Enable debug mode (default: false)
- `REDIS_HOST` - Redis hostname
- `REVERB_HOST` - Reverb hostname
- `REVERB_PORT` - Reverb port
- `REVERB_APP_ID` - Reverb application ID
- `REVERB_APP_KEY` - Reverb application key
- `REVERB_APP_SECRET` - Reverb application secret

## Health Checks

The image includes a health check that runs every 30 seconds:

```bash
docker ps  # Check health status in STATUS column
```

## Volumes

Mount these volumes for persistent data:

- `/var/www/html/storage` - Laravel storage (logs, cache, uploads)

## Security Features

### SBOM (Software Bill of Materials)

The image includes an SBOM attestation listing all components.

### Provenance

Build provenance attestations ensure reproducibility and security.

### Verify Attestations

```bash
# View SBOM
docker buildx imagetools inspect itrcglobe/breakster:latest --format "{{ json .SBOM }}"

# View Provenance
docker buildx imagetools inspect itrcglobe/breakster:latest --format "{{ json .Provenance }}"
```

## Troubleshooting

### Check Logs

```bash
# Web server logs
docker logs breakster-web

# Queue worker logs
docker logs breakster-queue

# Reverb logs
docker logs breakster-reverb
```

### Access Container Shell

```bash
docker exec -it breakster-web bash
```

### Run Artisan Commands

```bash
docker exec breakster-web php artisan migrate
docker exec breakster-web php artisan cache:clear
```
