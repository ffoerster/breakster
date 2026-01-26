#!/bin/bash

# Release script for Breakster Docker image
# Builds, tags, and pushes to Docker Hub with SBOM and provenance attestations

set -e  # Exit on any error

# Configuration
DOCKER_HUB_REPO="itrcglobe/breakster"
IMAGE_NAME="${DOCKER_HUB_REPO}"

# Prefer production env vars, falling back to local .env if needed
ENV_FILE=""
if [ -f ".env.production" ]; then
    ENV_FILE=".env.production"
elif [ -f ".env" ]; then
    ENV_FILE=".env"
fi

if [ -n "$ENV_FILE" ]; then
    echo "Loading build environment from ${ENV_FILE}"
    set -o allexport
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +o allexport
else
    echo "Warning: no .env.production or .env file found; relying on existing environment variables"
fi

# Map REVERB_* to VITE_* if VITE_* not explicitly set
VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY:-${REVERB_APP_KEY}}"
VITE_REVERB_HOST="${VITE_REVERB_HOST:-${REVERB_HOST}}"
VITE_REVERB_PORT="${VITE_REVERB_PORT:-${REVERB_PORT}}"
VITE_REVERB_SCHEME="${VITE_REVERB_SCHEME:-${REVERB_SCHEME}}"

REQUIRED_ENV_VARS=(
    "VITE_REVERB_APP_KEY"
    "VITE_REVERB_HOST"
    "VITE_REVERB_PORT"
    "VITE_REVERB_SCHEME"
)

for var in "${REQUIRED_ENV_VARS[@]}"; do
    if [ -z "${!var:-}" ]; then
        echo "Error: ${var} is not set. Ensure production env vars are available for the Docker build."
        exit 1
    fi
done

# Get version from argument or git tag
if [ -n "$1" ]; then
    VERSION="$1"
else
    # Try to get version from git tag
    VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "latest")
fi

echo "Building and releasing ${IMAGE_NAME}:${VERSION}"

# Ensure Docker Buildx is available
if ! docker buildx version >/dev/null 2>&1; then
    echo "Error: Docker Buildx is required for attestations"
    exit 1
fi

# Create and use a builder instance if not already using one
BUILDER_NAME="breakster-builder"
if ! docker buildx inspect "$BUILDER_NAME" >/dev/null 2>&1; then
    docker buildx create --name "$BUILDER_NAME" --use
fi

# Build and push with attestations
echo "Building image with SBOM and provenance attestations..."
docker buildx build \
    --platform linux/amd64 \
    --build-arg "VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}" \
    --build-arg "VITE_REVERB_HOST=${VITE_REVERB_HOST}" \
    --build-arg "VITE_REVERB_PORT=${VITE_REVERB_PORT}" \
    --build-arg "VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}" \
    --tag "${IMAGE_NAME}:${VERSION}" \
    --tag "${IMAGE_NAME}:latest" \
    --sbom=true \
    --provenance=true \
    --push \
    .

echo "Successfully pushed ${IMAGE_NAME}:${VERSION} and ${IMAGE_NAME}:latest to Docker Hub"
echo "Attestations (SBOM and provenance) have been included"

# Optional: Clean up builder
# docker buildx rm "$BUILDER_NAME"
