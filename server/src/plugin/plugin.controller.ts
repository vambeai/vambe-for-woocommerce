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
    const apiKey = this.configService.get<string>("API_KEY");
    if (!apiKey) {
      this.logger.warn("API key not configured in environment variables");
    }
  }

  @Post("download")
  async downloadPlugin(
    @Headers("x-api-key") apiKey: string,
    @Body() downloadPluginDto: DownloadPluginDto
  ): Promise<{ url: string }> {
    try {
      // Validate API key
      const validApiKey = this.configService.get<string>("API_KEY");

      if (!apiKey) {
        throw new UnauthorizedException("API key is required");
      }

      if (apiKey !== validApiKey) {
        throw new UnauthorizedException("Invalid API key");
      }

      // Validate client API key
      if (!downloadPluginDto.client_api_key) {
        throw new BadRequestException("Client API key is required");
      }

      // Generate the plugin with the client API key
      const downloadUrl = await this.pluginService.generatePlugin(
        downloadPluginDto.client_api_key
      );

      return { url: downloadUrl };
    } catch (error) {
      this.logger.error(`Error in downloadPlugin: ${error.message}`);

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
    // Check environment variables
    const apiKey = this.configService.get<string>("API_KEY");
    const serverUrl = this.configService.get<string>("SERVER_URL");

    if (!apiKey) {
      this.logger.warn("API_KEY not configured");
    }

    if (!serverUrl) {
      this.logger.warn("SERVER_URL not configured");
    }

    return {
      status: "ok",
      environment: process.env.NODE_ENV || "development",
      timestamp: new Date().toISOString(),
    };
  }
}
