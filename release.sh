#!/bin/bash

# Release script for Breakster Docker image
# Builds, tags, and pushes to Docker Hub with SBOM and provenance attestations

set -e  # Exit on any error

# Configuration
DOCKER_HUB_REPO="itrcglobe/breakster"
IMAGE_NAME="${DOCKER_HUB_REPO}"

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
