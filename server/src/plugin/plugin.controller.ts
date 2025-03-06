import {
  Controller,
  Post,
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

    try {
      // Validate API key
      this.logger.debug("Validating API key");
      const validApiKey = this.configService.get<string>("API_KEY");

      if (!apiKey) {
        this.logger.warn("API key missing in request headers");
        throw new UnauthorizedException("API key is required");
      }

      if (apiKey !== validApiKey) {
        this.logger.warn("Invalid API key provided");
        throw new UnauthorizedException("Invalid API key");
      }

      this.logger.debug("API key validation successful");

      // Validate client API key
      this.logger.debug("Validating client API key");
      if (!downloadPluginDto.client_api_key) {
        this.logger.warn("Client API key missing in request body");
        throw new BadRequestException("Client API key is required");
      }

      this.logger.debug(
        `Client API key validation successful: ${downloadPluginDto.client_api_key}`
      );

      // Generate the plugin with the client API key
      this.logger.log(
        `Generating plugin with client API key: ${downloadPluginDto.client_api_key}`
      );
      const downloadUrl = await this.pluginService.generatePlugin(
        downloadPluginDto.client_api_key
      );

      this.logger.log(
        `Plugin generated successfully. Download URL: ${downloadUrl}`
      );

      return { url: downloadUrl };
    } catch (error) {
      // Only log internal errors as errors, expected exceptions are already logged as warnings
      if (
        !(error instanceof UnauthorizedException) &&
        !(error instanceof BadRequestException)
      ) {
        this.logger.error(`Error generating plugin: ${error.message}`);
        this.logger.error(error.stack);
        throw new InternalServerErrorException("Failed to generate plugin");
      }

      throw error;
    }
  }
}
