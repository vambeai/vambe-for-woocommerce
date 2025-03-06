import { Module } from "@nestjs/common";
import { ConfigModule } from "@nestjs/config";
import { PluginModule } from "./plugin/plugin.module";

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
    }),
    PluginModule,
  ],
})
export class AppModule {}
