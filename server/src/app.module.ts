import { Module } from "@nestjs/common";
import { ConfigModule } from "@nestjs/config";
import { PluginModule } from "./plugin/plugin.module";
import { ScheduleModule } from "@nestjs/schedule";

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
    }),
    ScheduleModule.forRoot(),
    PluginModule,
  ],
})
export class AppModule {}
