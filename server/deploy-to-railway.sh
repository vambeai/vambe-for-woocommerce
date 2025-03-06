#!/bin/bash

# Make script executable with: chmod +x deploy-to-railway.sh

echo "Preparing to deploy to Railway..."

# Ensure we're in the server directory
cd "$(dirname "$0")"

# Check if the vambe_for_wc directory exists at the project root
if [ ! -d "../vambe_for_wc" ]; then
  echo "Error: vambe_for_wc directory not found at the project root."
  exit 1
fi

# Create a temporary directory for deployment
echo "Creating temporary deployment directory..."
TEMP_DIR=$(mktemp -d)
echo "Temporary directory: $TEMP_DIR"

# Copy server files to the temp directory
echo "Copying server files..."
cp -r ./* "$TEMP_DIR/"

# Create vambe_for_wc directory in the temp directory
echo "Creating vambe_for_wc directory in the deployment package..."
mkdir -p "$TEMP_DIR/vambe_for_wc"

# Copy vambe_for_wc files to the temp directory
echo "Copying vambe_for_wc files..."
cp -r ../vambe_for_wc/* "$TEMP_DIR/vambe_for_wc/"

# Navigate to the temp directory
cd "$TEMP_DIR"

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
