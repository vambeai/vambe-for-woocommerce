import { NestFactory } from "@nestjs/core";
import { AppModule } from "./app.module";
import { Logger } from "@nestjs/common";
import { NestExpressApplication } from "@nestjs/platform-express";
import * as express from "express";
import * as path from "path";
import * as fs from "fs";

async function bootstrap() {
  const logger = new Logger("Bootstrap");

  try {
    const app = await NestFactory.create<NestExpressApplication>(AppModule, {
      logger: ["error", "warn", "log"],
    });

    // Create public directory for serving static files
    const publicDir = path.resolve(process.cwd(), "public");
    const downloadDir = path.resolve(publicDir, "download");

    // Create directories if they don't exist
    if (!fs.existsSync(publicDir)) {
      fs.mkdirSync(publicDir, { recursive: true });
    }

    if (!fs.existsSync(downloadDir)) {
      fs.mkdirSync(downloadDir, { recursive: true });
    }

    // Serve static files from the public directory
    app.use("/download", express.static(downloadDir));

    // Enable CORS
    app.enableCors();

    // Add a root-level health check endpoint
    app.use("/", (req, res, next) => {
      if (req.method === "GET" && req.url === "/") {
        return res.json({
          status: "ok",
          service: "vambe-for-woocommerce",
          environment: process.env.NODE_ENV || "development",
          timestamp: new Date().toISOString(),
          port: process.env.PORT || 3003,
        });
      }
      next();
    });

    // Log middleware for request debugging (only in development)
    if (process.env.NODE_ENV !== "production") {
      app.use((req, res, next) => {
        logger.log(`Request: ${req.method} ${req.url}`);

        // Track response
        const originalSend = res.send;
        res.send = function (body) {
          logger.log(
            `Response: ${req.method} ${req.url} - Status: ${res.statusCode}`
          );
          return originalSend.call(this, body);
        };

        next();
      });
    }

    // Railway sets the PORT environment variable
    const port = process.env.PORT || 3003;

    // Bind to all interfaces (0.0.0.0) to ensure Railway can route traffic
    await app.listen(port, "0.0.0.0");

    logger.log(
      `Application running on port ${port} (${
        process.env.NODE_ENV || "development"
      })`
    );

    // Check for plugin directory (simplified)
    const possiblePaths = [
      path.resolve(process.cwd(), "../vambe_for_wc"),
      path.resolve(process.cwd(), "vambe_for_wc"),
      "/vambe_for_wc",
      "/app/vambe_for_wc",
    ];

    for (const pluginPath of possiblePaths) {
      if (fs.existsSync(pluginPath)) {
        logger.log(`Plugin directory found at: ${pluginPath}`);
        break;
      }
    }
  } catch (error) {
    logger.error(`Bootstrap error: ${error.message}`);
  }
}

bootstrap().catch((err) => {
  console.error("Fatal error during bootstrap:", err);
  process.exit(1);
});
