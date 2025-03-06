import * as nodeCrypto from "crypto";

// This function provides a compatible interface for the randomUUID function
// that the ScheduleModule is trying to use
export function getRandomId(): string {
  // Use Node.js crypto module to generate a UUID
  return nodeCrypto.randomUUID();
}

// This function provides a compatible interface for the getRandomValues function
// that might be used by the ScheduleModule
export function getRandomValues(array: Uint8Array): Uint8Array {
  // Use Node.js crypto module to fill the array with random values
  return nodeCrypto.randomFillSync(array);
}
