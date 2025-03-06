# Vambe WooCommerce Plugin Server

This is a NestJS server that provides an API endpoint for downloading a customized version of the Vambe WooCommerce plugin with a client-specific API key.

## Features

- Protected API endpoint with API key authentication
- Dynamically replaces the `{{CLIENT_TOKEN}}` placeholder in the plugin with a client-specific API key
- Compresses the plugin into a zip file
- Uploads the zip file to UploadThing
- Returns the URL to the uploaded file

## Prerequisites

- Node.js (v16 or higher)
- pnpm

## Installation

1. Clone the repository
2. Navigate to the server directory
3. Install dependencies:

```bash
pnpm install
```

4. Configure environment variables:

Copy the `.env.example` file to `.env` and update the values:

```
# API key for protecting the endpoint
API_KEY=your_api_key_here

# UploadThing API keys
UPLOADTHING_SECRET=your_uploadthing_secret_here
UPLOADTHING_APP_ID=your_uploadthing_app_id_here
```

## Running the server

### Development mode

```bash
pnpm start:dev
```

### Production mode

```bash
pnpm build
pnpm start:prod
```

## API Endpoints

### Download Plugin

```
POST /plugin/download
```

#### Headers

- `x-api-key`: Your API key for authentication

#### Request Body

```json
{
  "client_api_key": "client_specific_api_key_here"
}
```

#### Response

```json
{
  "url": "https://uploadthing.com/f/example-file-url.zip"
}
```

## How it works

1. The server receives a request with a client API key
2. It creates a copy of the Vambe WooCommerce plugin
3. It replaces the `{{CLIENT_TOKEN}}` placeholder in the plugin with the provided client API key
4. It compresses the modified plugin into a zip file
5. It uploads the zip file to UploadThing
6. It returns the URL to the uploaded file
7. It cleans up temporary files

## License

ISC
