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

2. Run the deployment script from the server directory:

   ```
   ./deploy-to-railway.sh
   ```

   This script will:

   - Create a temporary deployment package with the proper monorepo structure
   - Include both the server and the vambe_for_wc plugin directory
   - Include the Dockerfile for containerized deployment
   - Deploy to Railway
   - Clean up temporary files

### Option 2: Manual Deployment with Docker

1. Create a temporary directory for deployment
2. Set up the monorepo structure:
   ```
   temp-dir/
   ├── package.json             # Root package.json with workspaces
   ├── pnpm-workspace.yaml      # PNPM workspace config
   ├── railway.json             # Railway config
   ├── Dockerfile               # Docker configuration
   ├── .npmrc                   # PNPM config
   ├── server/                  # Server code
   └── vambe_for_wc/            # Plugin code
   ```
3. Copy all necessary files maintaining this structure
4. Deploy using the Railway CLI: `railway up`

### How the Docker Deployment Works

The deployment uses a Docker-based approach to ensure the plugin directory is properly included:

1. The Dockerfile:

   - Uses Node.js 18 Alpine as the base image
   - Installs pnpm and project dependencies
   - Builds the server application
   - Copies the vambe_for_wc directory to both /app/vambe_for_wc and /vambe_for_wc
   - Exposes port 8080 for the application
   - Runs the server application

2. This approach ensures that:
   - The plugin directory is available in the expected locations
   - All dependencies are properly installed
   - The application can find the plugin directory at runtime

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
