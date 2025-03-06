import {
  Controller,
  Post,
  Get,
  Body,
  Headers,
  UnauthorizedException,
  BadRequestException,
  Logger,
  InternalServerErrorException,
} from "@nestjs/common";
import { PluginService } from "./plugin.service";
import { ConfigService } from "@nestjs/config";

interface DownloadPluginDto {
  client_api_key: string;
}

@Controller("plugin")
export class PluginController {
  private readonly logger = new Logger(PluginController.name);

  constructor(
    private readonly pluginService: PluginService,
    private readonly configService: ConfigService
  ) {
    this.logger.log("PluginController initialized");

    // Log the API key from config (masked for security)
    const apiKey = this.configService.get<string>("API_KEY");
    if (apiKey) {
      const maskedKey =
        apiKey.substring(0, 3) + "***" + apiKey.substring(apiKey.length - 3);
      this.logger.log(`API key configured: ${maskedKey}`);
    } else {
      this.logger.warn("API key not configured in environment variables");
    }
  }

  @Post("download")
  async downloadPlugin(
    @Headers("x-api-key") apiKey: string,
    @Body() downloadPluginDto: DownloadPluginDto
  ): Promise<{ url: string }> {
    this.logger.log("Received download plugin request");
    this.logger.log(
      `Request headers: x-api-key=${apiKey ? "present" : "missing"}`
    );
    this.logger.log(`Request body: ${JSON.stringify(downloadPluginDto)}`);

    try {
      // Validate API key
      this.logger.log("Validating API key");
      const validApiKey = this.configService.get<string>("API_KEY");
      this.logger.log(
        `API_KEY from config: ${validApiKey ? "configured" : "missing"}`
      );

      if (!apiKey) {
        this.logger.warn("API key missing in request headers");
        throw new UnauthorizedException("API key is required");
      }

      if (apiKey !== validApiKey) {
        this.logger.warn("Invalid API key provided");
        throw new UnauthorizedException("Invalid API key");
      }

      this.logger.log("API key validation successful");

      // Validate client API key
      this.logger.log("Validating client API key");
      if (!downloadPluginDto.client_api_key) {
        this.logger.warn("Client API key missing in request body");
        throw new BadRequestException("Client API key is required");
      }

      this.logger.log(
        `Client API key validation successful: ${downloadPluginDto.client_api_key}`
      );

      // Generate the plugin with the client API key
      this.logger.log(
        `Generating plugin with client API key: ${downloadPluginDto.client_api_key}`
      );

      // Check if plugin directory exists before generating
      const fs = require("fs");
      const path = require("path");
      const possiblePaths = [
        path.resolve(process.cwd(), "../vambe_for_wc"),
        path.resolve(process.cwd(), "vambe_for_wc"),
        "/vambe_for_wc",
        "/app/vambe_for_wc",
      ];

      for (const pluginPath of possiblePaths) {
        this.logger.log(`Checking plugin path: ${pluginPath}`);
        if (fs.existsSync(pluginPath)) {
          this.logger.log(`Plugin directory found at: ${pluginPath}`);
          const files = fs.readdirSync(pluginPath);
          this.logger.log(`Files in directory: ${files.join(", ")}`);
          break;
        }
      }

      const downloadUrl = await this.pluginService.generatePlugin(
        downloadPluginDto.client_api_key
      );

      this.logger.log(
        `Plugin generated successfully. Download URL: ${downloadUrl}`
      );

      return { url: downloadUrl };
    } catch (error) {
      this.logger.error(`Error in downloadPlugin: ${error.message}`);
      this.logger.error(error.stack);

      if (
        error instanceof UnauthorizedException ||
        error instanceof BadRequestException
      ) {
        throw error;
      }

      throw new InternalServerErrorException(
        `Failed to generate plugin: ${error.message}`
      );
    }
  }

  @Get("health")
  healthCheck(): { status: string; environment: string; timestamp: string } {
    this.logger.log("Health check endpoint called");

    // Check if plugin directory exists
    const fs = require("fs");
    const path = require("path");
    const possiblePaths = [
      path.resolve(process.cwd(), "../vambe_for_wc"),
      path.resolve(process.cwd(), "vambe_for_wc"),
      "/vambe_for_wc",
      "/app/vambe_for_wc",
    ];

    let pluginPathFound = false;
    let foundPath = "";

    for (const pluginPath of possiblePaths) {
      this.logger.log(`Health check: Checking plugin path: ${pluginPath}`);
      if (fs.existsSync(pluginPath)) {
        this.logger.log(
          `Health check: Plugin directory found at: ${pluginPath}`
        );
        pluginPathFound = true;
        foundPath = pluginPath;
        break;
      }
    }

    if (!pluginPathFound) {
      this.logger.warn(
        "Health check: Plugin directory not found in any checked location"
      );
    }

    // Check environment variables
    const apiKey = this.configService.get<string>("API_KEY");
    const uploadthingSecret =
      this.configService.get<string>("UPLOADTHING_SECRET");
    const serverUrl = this.configService.get<string>("SERVER_URL");

    this.logger.log(
      `Health check: API_KEY configured: ${apiKey ? "Yes" : "No"}`
    );
    this.logger.log(
      `Health check: UPLOADTHING_SECRET configured: ${
        uploadthingSecret ? "Yes" : "No"
      }`
    );
    this.logger.log(
      `Health check: SERVER_URL configured: ${serverUrl || "Not set"}`
    );

    return {
      status: "ok",
      environment: process.env.NODE_ENV || "development",
      timestamp: new Date().toISOString(),
    };
  }
}
