#!/bin/bash

# Make script executable with: chmod +x deploy-to-railway.sh

echo "Preparing to deploy to Railway..."

# Ensure we're in the server directory
cd "$(dirname "$0")"
cd ..

# Check if the vambe_for_wc directory exists
if [ ! -d "vambe_for_wc" ]; then
  echo "Error: vambe_for_wc directory not found at the project root."
  exit 1
fi

# Check if Railway CLI is installed
if ! command -v railway &> /dev/null; then
  echo "Railway CLI not found. Please install it with: npm i -g @railway/cli"
  echo "Then login with: railway login"
  exit 1
fi

# Deploy to Railway
echo "Deploying to Railway..."
railway up

echo "Deployment complete!"
