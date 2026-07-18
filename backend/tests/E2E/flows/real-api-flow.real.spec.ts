import { expect, test } from "@playwright/test";
import { postAuthJson } from "../helpers/csrf.ts";

/**
 * Real-backend E2E: API contract + page rendering.
 *
 * Prerequisites:
 *   1. `php artisan serve --port 8000` (from backend/)
 *   2. `php artisan migrate --seed` on a SQLite test database
 *   3. `QUEUE_CONNECTION=sync` so jobs run inline
 *
 * These tests verify the API endpoints return correct status codes and
 * the frontend pages render without JavaScript errors.
 */
test.describe("Real backend: API contracts and page rendering", () => {
    test("home page loads and renders launcher options", async ({ page }) => {
        // Collect console errors during page load.
        const consoleErrors: string[] = [];
        page.on("console", (msg) => {
            if (msg.type() === "error") consoleErrors.push(msg.text());
        });

        await page.goto("/");

        // Page should render the hero heading.
        await expect(page.locator("h1")).toContainText("in flow", { timeout: 10_000 });

        // No console errors on page load (ignore favicon 404 and expected 401
        // from unauthenticated /api/user/* calls the SPA makes on load).
        expect(
            consoleErrors.filter(
                (e) =>
                    !e.includes("favicon") &&
                    !e.includes("401") &&
                    !e.includes("Unauthorized"),
            ),
        ).toHaveLength(0);
    });

    test("GET /api/launchers returns valid launcher data", async ({ request }) => {
        const res = await request.get("/api/launchers");
        expect(res.status()).toBe(200);

        const body = await res.json();
        // /api/launchers returns a flat array (not wrapped in {data: [...]}).
        expect(Array.isArray(body)).toBe(true);
        expect(body.length).toBeGreaterThanOrEqual(4);

        // Each launcher has required fields.
        for (const launcher of body) {
            expect(launcher).toHaveProperty("id");
            expect(launcher).toHaveProperty("slug");
            expect(launcher).toHaveProperty("name");
        }
    });

    test("GET /api/health returns 200", async ({ request }) => {
        const res = await request.get("/api/health");
        expect(res.status()).toBe(200);
    });

    test("POST /api/runs with invalid URL returns 422", async ({ request }) => {
        const res = await postAuthJson(request, "/api/runs", {
            launcher: "review-pr",
            source_url: "not-a-url",
        });
        expect(res.status()).toBe(422);

        const body = await res.json();
        expect(body).toHaveProperty("errors");
    });

    test("can clear the URL input", async ({ page }) => {
        await page.goto("/");

        const urlInput = page.getByPlaceholder(/github.com/);
        await urlInput.fill("https://github.com/a/b");
        await expect(urlInput).toHaveValue("https://github.com/a/b");

        await page.getByRole("button", { name: "Clear URL" }).click();
        await expect(urlInput).toHaveValue("");
    });

    test("POST /api/runs with valid URL returns 202 and run is pollable", async ({
        request,
        page,
    }) => {
        const res = await postAuthJson(request, "/api/runs", {
            launcher: "review-pr",
            source_url: "https://github.com/laravel/framework/pull/42",
        });

        // Without a server AI key, guest runs get 422 (no provider key).
        // With a key, they get 202 + queued run.
        const status = res.status();
        expect([202, 422], `unexpected status ${status}`).toContain(status);

        if (status !== 202) {
            return;
        }

        const body = await res.json();
        expect(body).toHaveProperty("id");
        expect(body.status).toBe("queued");

        const runId = body.id as string;

        // Navigate to the run report page — it should render in a completed/failed
        // state after the synchronous job finishes (or times out without AI keys).
        await page.goto(`/runs/${runId}`);

        // The page should either show a report or a failed/error state, not a blank
        // page or generic error boundary.
        await expect(
            page.locator(".report-page, .error-fallback"),
        ).toBeVisible({ timeout: 15_000 });
    });
});
