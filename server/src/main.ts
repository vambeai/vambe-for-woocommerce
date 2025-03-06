import { NestFactory } from "@nestjs/core";
import { AppModule } from "./app.module";
import { Logger } from "@nestjs/common";
import { NestExpressApplication } from "@nestjs/platform-express";
import * as express from "express";
import * as path from "path";
import * as fs from "fs";

async function bootstrap() {
  const logger = new Logger("Bootstrap");

  logger.log("Starting NestJS application...");

  try {
    const app = await NestFactory.create<NestExpressApplication>(AppModule, {
      logger: ["error", "warn", "log", "debug", "verbose"],
    });

    // Create public directory for serving static files
    const publicDir = path.resolve(process.cwd(), "public");
    const downloadDir = path.resolve(publicDir, "download");

    // Create directories if they don't exist
    if (!fs.existsSync(publicDir)) {
      logger.log(`Creating public directory: ${publicDir}`);
      fs.mkdirSync(publicDir, { recursive: true });
    }

    if (!fs.existsSync(downloadDir)) {
      logger.log(`Creating download directory: ${downloadDir}`);
      fs.mkdirSync(downloadDir, { recursive: true });
    }

    // Serve static files from the public directory
    app.use("/download", express.static(downloadDir));
    logger.log(`Serving static files from: ${downloadDir}`);

    // Enable CORS
    app.enableCors();

    // Log middleware for request debugging
    app.use((req, res, next) => {
      logger.log(`Incoming request: ${req.method} ${req.url}`);

      // Log request headers
      logger.debug(`Request headers: ${JSON.stringify(req.headers)}`);

      // Log request body if it exists
      if (req.body) {
        logger.debug(`Request body: ${JSON.stringify(req.body)}`);
      }

      // Track response
      const originalSend = res.send;
      res.send = function (body) {
        logger.log(
          `Response for ${req.method} ${req.url} - Status: ${res.statusCode}`
        );
        return originalSend.call(this, body);
      };

      next();
    });

    const port = process.env.PORT || 3003;
    await app.listen(port, "0.0.0.0");

    logger.log(`Application is running on: http://localhost:${port}`);
    logger.log(`Environment: ${process.env.NODE_ENV || "development"}`);

    // Check for plugin directory in various possible locations
    const possiblePaths = [
      path.resolve(process.cwd(), "../vambe_for_wc"),
      path.resolve(process.cwd(), "vambe_for_wc"),
      path.resolve(process.cwd(), "../../vambe_for_wc"),
      path.resolve(process.cwd(), "../../../vambe_for_wc"),
      path.resolve(process.cwd(), "../../../../vambe_for_wc"),
      // Railway deployment paths
      "/vambe_for_wc",
      "/app/vambe_for_wc",
      path.resolve("/app", "../vambe_for_wc"),
    ];

    logger.log(`Current working directory: ${process.cwd()}`);
    logger.log(`Checking possible plugin paths:`);

    let pluginPathFound = false;
    for (const pluginPath of possiblePaths) {
      logger.log(`Checking path: ${pluginPath}`);
      if (fs.existsSync(pluginPath)) {
        logger.log(`Plugin directory exists at: ${pluginPath}`);
        const files = fs.readdirSync(pluginPath);
        logger.log(
          `Plugin directory contains ${files.length} files/directories`
        );

        // List some files to verify it's the correct directory
        logger.log(`Files in plugin directory:`);
        files.slice(0, 5).forEach((file) => {
          logger.log(`- ${file}`);
        });

        pluginPathFound = true;
        break;
      }
    }

    if (!pluginPathFound) {
      logger.error(
        `Plugin directory not found in any of the checked locations`
      );
    }
  } catch (error) {
    logger.error(`Error during application bootstrap: ${error.message}`);
    logger.error(error.stack);
  }
}

bootstrap().catch((err) => {
  console.error("Fatal error during bootstrap:", err);
  process.exit(1);
});
