import {
  Injectable,
  BadRequestException,
  InternalServerErrorException,
  OnModuleInit,
  OnModuleDestroy,
} from "@nestjs/common";
import { ConfigService } from "@nestjs/config";
import * as fs from "fs";
import * as path from "path";
import * as AdmZip from "adm-zip";

@Injectable()
export class PluginService {
  private readonly pluginPath: string;
  private readonly downloadDir: string;

  constructor(private configService: ConfigService) {
    // Initialize download directory
    this.downloadDir = path.resolve(process.cwd(), "public", "download");
    // Path to the plugin directory relative to the current working directory
    // Try different paths to find the plugin directory
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

    console.log("[PluginService] Current working directory:", process.cwd());
    console.log("[PluginService] Checking possible plugin paths:");

    // Find the first path that exists
    let foundPath = null;
    for (const p of possiblePaths) {
      console.log(`[PluginService] Checking path: ${p}`);
      if (fs.existsSync(p)) {
        console.log(`[PluginService] Found plugin directory at: ${p}`);
        foundPath = p;
        break;
      }
    }

    if (foundPath) {
      this.pluginPath = foundPath;
    } else {
      // Default to the original path and log a warning
      this.pluginPath = path.resolve(process.cwd(), "../vambe_for_wc");
      console.warn(
        `[PluginService] Could not find plugin directory. Using default path: ${this.pluginPath}`
      );
    }

    // Ensure the download directory exists
    if (!fs.existsSync(this.downloadDir)) {
      fs.mkdirSync(this.downloadDir, { recursive: true });
    }
  }

  async generatePlugin(clientApiKey: string): Promise<string> {
    try {
      console.log(
        `[generatePlugin] Starting plugin generation for client API key: ${clientApiKey}`
      );

      // Create a temporary directory for the modified plugin
      const tempDir = path.resolve(process.cwd(), "temp");
      console.log(`[generatePlugin] Temporary directory path: ${tempDir}`);

      if (!fs.existsSync(tempDir)) {
        console.log(`[generatePlugin] Creating temporary directory`);
        fs.mkdirSync(tempDir, { recursive: true });
      }

      // Create a copy of the plugin with the client API key
      console.log(`[generatePlugin] Creating modified plugin`);
      const modifiedPluginPath = await this.createModifiedPlugin(
        clientApiKey,
        tempDir
      );
      console.log(
        `[generatePlugin] Modified plugin created at: ${modifiedPluginPath}`
      );

      // Create a zip file of the modified plugin
      const zipPath = path.resolve(tempDir, "vambe_for_wc.zip");
      console.log(`[generatePlugin] Creating zip file at: ${zipPath}`);
      await this.createZipFile(modifiedPluginPath, zipPath);

      // Check if zip file was created successfully
      if (fs.existsSync(zipPath)) {
        const stats = fs.statSync(zipPath);
        console.log(
          `[generatePlugin] Zip file created successfully. Size: ${stats.size} bytes`
        );
      } else {
        console.error(
          `[generatePlugin] Zip file was not created at: ${zipPath}`
        );
      }

      // Upload the zip file to UploadThing
      console.log(`[generatePlugin] Uploading zip file`);
      const uploadUrl = await this.uploadZipFile(zipPath);
      console.log(`[generatePlugin] Upload completed. URL: ${uploadUrl}`);

      // Clean up temporary files
      console.log(`[generatePlugin] Cleaning up temporary files`);
      this.cleanupTempFiles(tempDir);

      console.log(`[generatePlugin] Plugin generation completed successfully`);
      return uploadUrl;
    } catch (error) {
      console.error("Error generating plugin:", error);
      throw new InternalServerErrorException("Failed to generate plugin");
    }
  }

  private async createModifiedPlugin(
    clientApiKey: string,
    tempDir: string
  ): Promise<string> {
    try {
      console.log(`[createModifiedPlugin] Starting plugin modification`);

      // Create a copy of the plugin directory
      const modifiedPluginPath = path.resolve(tempDir, "vambe_for_wc");
      console.log(
        `[createModifiedPlugin] Modified plugin path: ${modifiedPluginPath}`
      );

      // Check if original plugin directory exists
      if (!fs.existsSync(this.pluginPath)) {
        console.error(
          `[createModifiedPlugin] Original plugin directory not found at: ${this.pluginPath}`
        );
        throw new Error(
          `Original plugin directory not found at: ${this.pluginPath}`
        );
      }

      console.log(
        `[createModifiedPlugin] Copying files from: ${this.pluginPath} to: ${modifiedPluginPath}`
      );

      // Copy all files from the original plugin to the modified plugin directory
      this.copyDirectory(this.pluginPath, modifiedPluginPath);

      // Check if files were copied successfully
      if (fs.existsSync(modifiedPluginPath)) {
        const files = fs.readdirSync(modifiedPluginPath);
        console.log(
          `[createModifiedPlugin] Files copied successfully. File count: ${files.length}`
        );
      } else {
        console.error(
          `[createModifiedPlugin] Failed to copy files to: ${modifiedPluginPath}`
        );
      }

      // Replace the client API key in the main plugin file
      const mainPluginFile = path.resolve(
        modifiedPluginPath,
        "vambe-for-wc.php"
      );
      console.log(
        `[createModifiedPlugin] Main plugin file path: ${mainPluginFile}`
      );

      // Check if main plugin file exists
      if (!fs.existsSync(mainPluginFile)) {
        console.error(
          `[createModifiedPlugin] Main plugin file not found at: ${mainPluginFile}`
        );
        throw new Error(`Main plugin file not found at: ${mainPluginFile}`);
      }

      let content = fs.readFileSync(mainPluginFile, "utf8");
      console.log(
        `[createModifiedPlugin] Read main plugin file. Content length: ${content.length}`
      );

      // Replace the placeholder with the actual client API key
      const regex = /['"]{{VAMBE_CLIENT_TOKEN}}['"]/;
      const replacement = `'${clientApiKey}'`;

      if (regex.test(content)) {
        console.log(`[createModifiedPlugin] Found placeholder in content`);
      } else {
        console.warn(
          `[createModifiedPlugin] Placeholder not found in content. Will attempt replacement anyway.`
        );
      }

      content = content.replace(regex, replacement);
      console.log(
        `[createModifiedPlugin] Replaced placeholder with client API key`
      );

      // Write the modified content back to the file
      fs.writeFileSync(mainPluginFile, content);
      console.log(`[createModifiedPlugin] Wrote modified content back to file`);

      return modifiedPluginPath;
    } catch (error) {
      console.error("Error creating modified plugin:", error);
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

      // Add the entire directory to the zip file
      zip.addLocalFolder(sourcePath);

      // Write the zip file to disk
      zip.writeZip(zipPath);
    } catch (error) {
      console.error("Error creating zip file:", error);
      throw new InternalServerErrorException("Failed to create zip file");
    }
  }

  // Cleanup interval reference
  private cleanupInterval: NodeJS.Timeout;

  onModuleInit() {
    // Set up cleanup to run every hour using setInterval instead of cron
    console.log("[PluginService] Setting up cleanup interval");
    this.cleanupInterval = setInterval(() => {
      console.log("[PluginService] Running scheduled cleanup via interval");
      this.cleanupOldFiles();
    }, 60 * 60 * 1000); // 1 hour in milliseconds

    // Run initial cleanup
    console.log("[PluginService] Running initial cleanup");
    this.cleanupOldFiles();
  }

  onModuleDestroy() {
    // Clean up the interval when the module is destroyed
    if (this.cleanupInterval) {
      console.log("[PluginService] Clearing cleanup interval");
      clearInterval(this.cleanupInterval);
    }
  }

  private async uploadZipFile(zipPath: string): Promise<string> {
    try {
      console.log(`[uploadZipFile] Starting file upload for: ${zipPath}`);

      // Check if zip file exists
      if (!fs.existsSync(zipPath)) {
        console.error(`[uploadZipFile] Zip file not found at: ${zipPath}`);
        throw new Error(`Zip file not found at: ${zipPath}`);
      }

      const stats = fs.statSync(zipPath);
      console.log(`[uploadZipFile] Zip file size: ${stats.size} bytes`);

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

      console.log(
        `[uploadZipFile] Copied zip file to public download directory: ${publicZipPath}`
      );

      // Return local URL
      const fileUrl = `${serverUrl}${downloadPath}`;
      console.log(`[uploadZipFile] File available at: ${fileUrl}`);
      return fileUrl;
    } catch (error) {
      console.error("Error uploading zip file:", error);
      throw new InternalServerErrorException("Failed to upload zip file");
    }
  }

  /**
   * Cleans up files in the download directory that are older than 3 hours
   */
  cleanupOldFiles(): void {
    try {
      console.log(
        `[cleanupOldFiles] Starting cleanup of old files in: ${this.downloadDir}`
      );

      if (!fs.existsSync(this.downloadDir)) {
        console.log(
          `[cleanupOldFiles] Download directory does not exist, nothing to clean up`
        );
        return;
      }

      const files = fs.readdirSync(this.downloadDir);
      const now = Date.now();
      const threeHoursInMs = 2 * 60 * 60 * 1000; // 2 hours in milliseconds (3 * 60 * 60 * 1000)

      let deletedCount = 0;
      for (const file of files) {
        const filePath = path.join(this.downloadDir, file);
        const stats = fs.statSync(filePath);
        const fileAge = now - stats.mtimeMs;

        if (fileAge > threeHoursInMs) {
          console.log(
            `[cleanupOldFiles] Deleting old file: ${filePath} (age: ${
              fileAge / 1000 / 60 / 60
            } hours)`
          );
          fs.unlinkSync(filePath);
          deletedCount++;
        }
      }

      console.log(
        `[cleanupOldFiles] Cleanup completed. Deleted ${deletedCount} files.`
      );
    } catch (error) {
      console.error(`[cleanupOldFiles] Error cleaning up old files:`, error);
      // Don't throw an exception here, just log the error
    }
  }

  private cleanupTempFiles(tempDir: string): void {
    try {
      console.log(`[cleanupTempFiles] Starting cleanup of: ${tempDir}`);

      // Remove the temporary directory and all its contents
      if (fs.existsSync(tempDir)) {
        console.log(
          `[cleanupTempFiles] Temporary directory exists, removing it`
        );
        fs.rmSync(tempDir, { recursive: true, force: true });

        // Verify cleanup
        if (!fs.existsSync(tempDir)) {
          console.log(
            `[cleanupTempFiles] Temporary directory removed successfully`
          );
        } else {
          console.warn(
            `[cleanupTempFiles] Failed to remove temporary directory`
          );
        }
      } else {
        console.log(
          `[cleanupTempFiles] Temporary directory does not exist, nothing to clean up`
        );
      }
    } catch (error) {
      console.error(
        `[cleanupTempFiles] Error cleaning up temporary files:`,
        error
      );
      // Don't throw an exception here, just log the error
    }
  }
}
