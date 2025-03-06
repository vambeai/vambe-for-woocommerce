# Vambe WooCommerce Plugin Server

This server provides functionality for generating customized Vambe WooCommerce plugins with client-specific API keys.

## Development Setup

1. Clone the repository
2. Install dependencies:
   ```
   npm install
   ```
3. Create a `.env` file based on `.env.example` and fill in your API keys
4. Start the development server:
   ```
   npm run start:dev
   ```

## Deploying to Railway

### Option 1: Using the Deployment Script (Recommended)

1. Make sure you have the Railway CLI installed:

   ```
   npm i -g @railway/cli
   railway login
   ```

2. Run the deployment script:

   ```
   ./deploy-to-railway.sh
   ```

   This script will:

   - Create a temporary deployment package
   - Include both the server and the vambe_for_wc plugin directory
   - Deploy to Railway
   - Clean up temporary files

### Option 2: Manual Deployment

1. Create a temporary directory for deployment
2. Copy both the server files and the vambe_for_wc directory into it
3. Make sure the vambe_for_wc directory is at the same level as the server files
4. Deploy using the Railway CLI or dashboard

## Environment Variables

The following environment variables need to be set in Railway:

- `API_KEY`: Your API key for protecting the endpoint
- `UPLOADTHING_SECRET`: Your UploadThing secret key
- `UPLOADTHING_APP_ID`: Your UploadThing app ID
- `SERVER_URL`: The URL of your deployed server (e.g., https://your-app.railway.app)

## Project Structure

- `src/`: Source code
  - `main.ts`: Application entry point
  - `app.module.ts`: Main application module
  - `plugin/`: Plugin module for handling plugin generation and downloads
