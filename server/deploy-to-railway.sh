#!/bin/bash

# Make script executable with: chmod +x deploy-to-railway.sh

echo "Preparing to deploy to Railway..."

# Ensure we're in the server directory
cd "$(dirname "$0")"
SERVER_DIR=$(pwd)
PROJECT_ROOT=$(dirname "$SERVER_DIR")

# Check if the vambe_for_wc directory exists at the project root
if [ ! -d "$PROJECT_ROOT/vambe_for_wc" ]; then
  echo "Error: vambe_for_wc directory not found at the project root."
  exit 1
fi

# Create a temporary directory for deployment
echo "Creating temporary deployment directory..."
TEMP_DIR=$(mktemp -d)
echo "Temporary directory: $TEMP_DIR"

# Copy root project files to the temp directory
echo "Copying project files..."
cp "$PROJECT_ROOT/package.json" "$TEMP_DIR/"
cp "$PROJECT_ROOT/pnpm-workspace.yaml" "$TEMP_DIR/"
cp "$PROJECT_ROOT/railway.json" "$TEMP_DIR/"
cp "$PROJECT_ROOT/Dockerfile" "$TEMP_DIR/"
cp "$PROJECT_ROOT/.npmrc" "$TEMP_DIR/"

# Create server directory in the temp directory
echo "Creating server directory in the deployment package..."
mkdir -p "$TEMP_DIR/server"

# Copy server files to the temp directory
echo "Copying server files..."
cp -r "$SERVER_DIR"/* "$TEMP_DIR/server/"

# Create vambe_for_wc directory in the temp directory
echo "Creating vambe_for_wc directory in the deployment package..."
mkdir -p "$TEMP_DIR/vambe_for_wc"

# Copy vambe_for_wc files to the temp directory
echo "Copying vambe_for_wc files..."
cp -r "$PROJECT_ROOT/vambe_for_wc"/* "$TEMP_DIR/vambe_for_wc/"

# Navigate to the temp directory
cd "$TEMP_DIR"

# Install dependencies
echo "Installing dependencies..."
pnpm install

# Check if Railway CLI is installed
if ! command -v railway &> /dev/null; then
  echo "Railway CLI not found. Please install it with: npm i -g @railway/cli"
  echo "Then login with: railway login"
  echo ""
  echo "Deployment files are prepared at: $TEMP_DIR"
  echo "You can manually deploy with: cd $TEMP_DIR && railway up"
  exit 1
fi

# Deploy to Railway
echo "Deploying to Railway..."
railway up

echo "Deployment complete!"
echo "Cleaning up temporary directory..."
rm -rf "$TEMP_DIR"

echo "Done!"
