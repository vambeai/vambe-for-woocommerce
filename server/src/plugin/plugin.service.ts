import {
  Injectable,
  InternalServerErrorException,
  Logger,
  OnModuleInit,
  OnModuleDestroy,
} from "@nestjs/common";
import { ConfigService } from "@nestjs/config";
import * as fs from "fs";
import * as path from "path";
import * as AdmZip from "adm-zip";

@Injectable()
export class PluginService implements OnModuleInit, OnModuleDestroy {
  private readonly pluginPath: string;
  private readonly downloadDir: string;
  private readonly logger = new Logger(PluginService.name);
  private cleanupInterval: NodeJS.Timeout;

  constructor(private configService: ConfigService) {
    // Initialize download directory
    this.downloadDir = path.resolve(process.cwd(), "public", "download");

    // Find plugin directory from possible paths
    const possiblePaths = [
      path.resolve(process.cwd(), "../vambe_for_wc"),
      path.resolve(process.cwd(), "vambe_for_wc"),
      "/vambe_for_wc",
      "/app/vambe_for_wc",
    ];

    // Find the first path that exists
    const foundPath = possiblePaths.find((p) => fs.existsSync(p));

    if (foundPath) {
      this.pluginPath = foundPath;
      this.logger.log(`Plugin directory found at: ${this.pluginPath}`);
    } else {
      // Default to the original path and log a warning
      this.pluginPath = path.resolve(process.cwd(), "../vambe_for_wc");
      this.logger.warn(
        `Plugin directory not found. Using default path: ${this.pluginPath}`
      );
    }

    // Ensure the download directory exists
    if (!fs.existsSync(this.downloadDir)) {
      fs.mkdirSync(this.downloadDir, { recursive: true });
    }
  }

  onModuleInit() {
    // Set up cleanup to run every hour
    this.cleanupInterval = setInterval(() => {
      this.cleanupOldFiles();
    }, 60 * 60 * 1000); // 1 hour in milliseconds

    // Run initial cleanup
    this.cleanupOldFiles();
  }

  onModuleDestroy() {
    // Clean up the interval when the module is destroyed
    if (this.cleanupInterval) {
      clearInterval(this.cleanupInterval);
    }
  }

  async generatePlugin(
    clientApiKey: string,
    externalId: string
  ): Promise<string> {
    try {
      // Create a temporary directory for the modified plugin
      const tempDir = path.resolve(process.cwd(), "temp");

      if (!fs.existsSync(tempDir)) {
        fs.mkdirSync(tempDir, { recursive: true });
      }

      // Create a copy of the plugin with the client API key
      const modifiedPluginPath = await this.createModifiedPlugin(
        clientApiKey,
        externalId,
        tempDir
      );

      // Create a zip file of the modified plugin
      const zipPath = path.resolve(tempDir, "vambe_for_wc.zip");
      await this.createZipFile(modifiedPluginPath, zipPath);

      // Upload the zip file
      const uploadUrl = await this.uploadZipFile(zipPath);

      // Clean up temporary files
      this.cleanupTempFiles(tempDir);

      return uploadUrl;
    } catch (error) {
      this.logger.error(`Error generating plugin: ${error.message}`);
      throw new InternalServerErrorException("Failed to generate plugin");
    }
  }

  private async createModifiedPlugin(
    clientApiKey: string,
    externalId: string,
    tempDir: string
  ): Promise<string> {
    try {
      // Create a copy of the plugin directory
      const modifiedPluginPath = path.resolve(tempDir, "vambe_for_wc");

      // Check if original plugin directory exists
      if (!fs.existsSync(this.pluginPath)) {
        throw new Error(
          `Original plugin directory not found at: ${this.pluginPath}`
        );
      }

      // Copy all files from the original plugin to the modified plugin directory
      this.copyDirectory(this.pluginPath, modifiedPluginPath);

      // Replace the client API key in the main plugin file
      const mainPluginFile = path.resolve(
        modifiedPluginPath,
        "vambe-for-wc.php"
      );

      // Check if main plugin file exists
      if (!fs.existsSync(mainPluginFile)) {
        throw new Error(`Main plugin file not found at: ${mainPluginFile}`);
      }

      let content = fs.readFileSync(mainPluginFile, "utf8");

      // Replace the placeholder with the actual client API key
      const regex = /['"]{{VAMBE_CLIENT_TOKEN}}['"]/;
      const replacement = `'${clientApiKey}'`;
      content = content.replace(regex, replacement);

      // Replace the placeholder with the actual external ID
      const regexExternalId = /['"]{{VAMBE_EXTERNAL_ID}}['"]/;
      const replacementExternalId = `'${externalId}'`;
      content = content.replace(regexExternalId, replacementExternalId);

      // Write the modified content back to the file
      fs.writeFileSync(mainPluginFile, content);

      return modifiedPluginPath;
    } catch (error) {
      this.logger.error(`Error creating modified plugin: ${error.message}`);
      throw new InternalServerErrorException(
        "Failed to create modified plugin"
      );
    }
  }

  private copyDirectory(source: string, destination: string): void {
    // Create the destination directory if it doesn't exist
    if (!fs.existsSync(destination)) {
      fs.mkdirSync(destination, { recursive: true });
    }

    // Get all files and directories in the source directory
    const entries = fs.readdirSync(source, { withFileTypes: true });

    // Copy each entry to the destination directory
    for (const entry of entries) {
      const sourcePath = path.join(source, entry.name);
      const destinationPath = path.join(destination, entry.name);

      if (entry.isDirectory()) {
        // Recursively copy subdirectories
        this.copyDirectory(sourcePath, destinationPath);
      } else {
        // Copy files
        fs.copyFileSync(sourcePath, destinationPath);
      }
    }
  }

  private async createZipFile(
    sourcePath: string,
    zipPath: string
  ): Promise<void> {
    try {
      const zip = new AdmZip();
      zip.addLocalFolder(sourcePath);
      zip.writeZip(zipPath);
    } catch (error) {
      this.logger.error(`Error creating zip file: ${error.message}`);
      throw new InternalServerErrorException("Failed to create zip file");
    }
  }

  private async uploadZipFile(zipPath: string): Promise<string> {
    try {
      // Check if zip file exists
      if (!fs.existsSync(zipPath)) {
        throw new Error(`Zip file not found at: ${zipPath}`);
      }

      // Create a unique filename with timestamp
      const timestamp = Date.now();
      const uniqueFilename = `vambe_for_wc_${timestamp}.zip`;

      // Create a local URL that can be used to download the file
      const serverUrl =
        this.configService.get<string>("SERVER_URL") || "http://localhost:3003";
      const downloadPath = `/download/${uniqueFilename}`;

      // Copy the zip file to the download directory with the unique filename
      const publicZipPath = path.resolve(this.downloadDir, uniqueFilename);
      fs.copyFileSync(zipPath, publicZipPath);

      // Return local URL
      return `${serverUrl}${downloadPath}`;
    } catch (error) {
      this.logger.error(`Error uploading zip file: ${error.message}`);
      throw new InternalServerErrorException("Failed to upload zip file");
    }
  }

  /**
   * Cleans up files in the download directory that are older than 3 hours
   */
  cleanupOldFiles(): void {
    try {
      if (!fs.existsSync(this.downloadDir)) {
        return;
      }

      const files = fs.readdirSync(this.downloadDir);
      const now = Date.now();
      const threeHoursInMs = 3 * 60 * 60 * 1000; // 3 hours in milliseconds

      let deletedCount = 0;
      for (const file of files) {
        const filePath = path.join(this.downloadDir, file);
        const stats = fs.statSync(filePath);
        const fileAge = now - stats.mtimeMs;

        if (fileAge > threeHoursInMs) {
          fs.unlinkSync(filePath);
          deletedCount++;
        }
      }

      if (deletedCount > 0) {
        this.logger.log(`Cleanup completed. Deleted ${deletedCount} files.`);
      }
    } catch (error) {
      this.logger.error(`Error cleaning up old files: ${error.message}`);
    }
  }

  private cleanupTempFiles(tempDir: string): void {
    try {
      // Remove the temporary directory and all its contents
      if (fs.existsSync(tempDir)) {
        fs.rmSync(tempDir, { recursive: true, force: true });
      }
    } catch (error) {
      this.logger.error(`Error cleaning up temporary files: ${error.message}`);
    }
  }
}
