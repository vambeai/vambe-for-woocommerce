FROM node:18-alpine

WORKDIR /app

# Install pnpm
RUN npm install -g pnpm

# Copy server files
COPY server ./server/

# Copy plugin files to both locations
COPY vambe_for_wc ./vambe_for_wc/

# Debug: List directories to verify
RUN echo "Contents of /app:" && ls -la /app && \
    echo "Contents of /app/vambe_for_wc:" && ls -la /app/vambe_for_wc

# Create root-level plugin directory
RUN mkdir -p /vambe_for_wc && \
    cp -r /app/vambe_for_wc/* /vambe_for_wc/ && \
    echo "Contents of /vambe_for_wc:" && ls -la /vambe_for_wc

# Install dependencies
RUN cd server && pnpm install

# Build the application
RUN cd server && pnpm run build

# Expose the port (Railway will set the PORT environment variable)
EXPOSE ${PORT:-8080}

# Start the application
CMD ["sh", "-c", "cd server && pnpm run start:prod"]
