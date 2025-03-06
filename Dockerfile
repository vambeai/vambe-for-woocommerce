FROM node:18-alpine

WORKDIR /app

# Install pnpm
RUN npm install -g pnpm

# Copy package files
COPY package.json pnpm-workspace.yaml .npmrc ./

# Copy server files
COPY server ./server/

# Copy plugin files
COPY vambe_for_wc ./vambe_for_wc/

# Install dependencies
RUN pnpm install

# Build the application
RUN cd server && pnpm run build

# Create plugin directories in expected locations
RUN mkdir -p /vambe_for_wc
RUN cp -r /app/vambe_for_wc/* /vambe_for_wc/

# Expose the port (Railway will set the PORT environment variable)
EXPOSE ${PORT:-8080}

# Start the application
CMD ["sh", "-c", "cd server && pnpm run start:prod"]
