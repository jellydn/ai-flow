import { fileURLToPath } from "node:url";
import { defineConfig, devices } from "@playwright/test";

const rootDir = fileURLToPath(new URL(".", import.meta.url));
const PORT = 8000;
const baseURL = `http://localhost:${PORT}`;

const webServerProfile = process.env.PLAYWRIGHT_WEB_SERVER ?? "demo";
const webServer =
    webServerProfile === "none"
        ? undefined
        : {
              command:
                  webServerProfile === "real"
                      ? "bash scripts/e2e/serve-real.sh"
                      : "bash scripts/e2e/serve-demo.sh",
              port: PORT,
              reuseExistingServer: !process.env.CI,
              cwd: rootDir,
              timeout: 180_000,
          };

export default defineConfig({
    testDir: "./tests/E2E",
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? "github" : "list",
    use: {
        baseURL,
        trace: "on-first-retry",
        screenshot: "only-on-failure",
    },
    webServer,

    projects: [
        {
            name: "demo",
            testMatch: "**/*.spec.ts",
            testIgnore: "**/*.real.spec.ts",
            use: { ...devices["Desktop Chrome"] },
        },
        {
            name: "real-backend",
            testMatch: "**/*.real.spec.ts",
            use: { ...devices["Desktop Chrome"] },
        },
    ],
});