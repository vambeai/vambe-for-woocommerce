#!/bin/bash

echo "Starting Railway build process..."

# Display current directory and files
echo "Current directory: $(pwd)"
echo "Files in current directory:"
ls -la

# Check if vambe_for_wc directory exists
if [ -d "vambe_for_wc" ]; then
  echo "vambe_for_wc directory found in current directory"
else
  echo "vambe_for_wc directory NOT found in current directory"
fi

# Create vambe_for_wc directory in /app
echo "Creating vambe_for_wc directory in /app"
mkdir -p /app/vambe_for_wc

# Copy vambe_for_wc files to /app/vambe_for_wc
if [ -d "vambe_for_wc" ]; then
  echo "Copying vambe_for_wc files to /app/vambe_for_wc"
  cp -r vambe_for_wc/* /app/vambe_for_wc/
  
  # Verify copy
  echo "Files in /app/vambe_for_wc:"
  ls -la /app/vambe_for_wc
else
  echo "ERROR: Cannot copy vambe_for_wc files - directory not found"
fi

# Also create vambe_for_wc at root level
echo "Creating vambe_for_wc directory at root level"
mkdir -p /vambe_for_wc

# Copy vambe_for_wc files to /vambe_for_wc
if [ -d "vambe_for_wc" ]; then
  echo "Copying vambe_for_wc files to /vambe_for_wc"
  cp -r vambe_for_wc/* /vambe_for_wc/
  
  # Verify copy
  echo "Files in /vambe_for_wc:"
  ls -la /vambe_for_wc
else
  echo "ERROR: Cannot copy vambe_for_wc files - directory not found"
fi

echo "Railway build process completed"
