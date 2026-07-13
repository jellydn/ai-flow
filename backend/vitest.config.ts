import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: "jsdom",
    setupFiles: ["./resources/ts/test/setup.ts"],
    include: ["resources/ts/**/*.test.{ts,tsx}"],
  },
});
