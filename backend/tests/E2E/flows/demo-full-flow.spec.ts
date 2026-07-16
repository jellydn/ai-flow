import { expect, test } from "@playwright/test";
import { authCard, authTabPanel } from "../helpers/authCard.ts";
import { postAuthJson } from "../helpers/csrf.ts";

/**
 * UI E2E against the real Laravel app (`scripts/e2e/serve-real.sh`).
 */
test.describe("Home and launcher UI", () => {
    test("sign-up tab shows labeled fields and link to sign in", async ({ page }) => {
        await page.goto("/");
        await page.getByRole("button", { name: "Sign in" }).click();

        const card = authCard(page);
        await card.getByRole("tab", { name: "Sign up" }).click();
        const signUp = authTabPanel(page, "Sign up");

        await expect(signUp.getByLabel("Email")).toBeVisible();
        await expect(signUp.getByLabel("Password", { exact: true })).toBeVisible();
        await expect(signUp.getByLabel("Confirm password")).toBeVisible();
        await expect(signUp.getByRole("button", { name: "Create account" })).toBeVisible();
        await expect(signUp.getByRole("button", { name: /^Sign in$/ })).toBeVisible();
    });

    test("shows sign-in UI when clicking Sign in button", async ({ page }) => {
        await page.goto("/");

        await page.getByRole("button", { name: "Sign in" }).click();

        const card = authCard(page);
        await expect(card).toBeVisible({ timeout: 5000 });
        await expect(page.getByPlaceholder(/you@example.com/)).toBeVisible();
        await expect(card.getByRole("button", { name: "Sign in", exact: true })).toBeVisible();

        await card.getByRole("tab", { name: "Email link" }).click();
        await expect(card.getByRole("button", { name: /Send sign-in link/ })).toBeVisible();

        await page.goto("/");
        await expect(page.locator("h1")).toContainText("in flow");
    });

    test("POST /api/runs accepts a valid launch when a provider key is configured", async ({
        request,
    }) => {
        const health = await request.get("/api/health");
        expect(health.status()).toBe(200);

        const response = await postAuthJson(request, "/api/runs", {
            launcher: "review-pr",
            source_url: "https://github.com/laravel/framework/pull/1",
        });

        if (!process.env.OPENAI_API_KEY) {
            expect(response.status()).toBe(422);
            return;
        }

        expect(response.status()).toBe(202);
        const body = await response.json();
        expect(body).toMatchObject({ status: "queued" });
        expect(typeof body.id).toBe("string");
    });

    test("launch starts a run and shows the running view", async ({ page }) => {
        test.skip(
            !process.env.OPENAI_API_KEY,
            "Requires OPENAI_API_KEY (or server key) for POST /api/runs",
        );

        await page.goto("/");

        await expect(page.locator("h1")).toContainText("in flow");
        await expect(page.locator(".launcher-card")).toBeVisible();

        const urlInput = page.getByPlaceholder(/github.com/);
        await urlInput.fill("https://github.com/laravel/framework/pull/42");

        await page
            .locator(".launcher-card")
            .getByRole("button", { name: /Launch workflow/ })
            .click();

        await expect(page.locator(".running-page")).toBeVisible({ timeout: 10_000 });
        await expect(page).toHaveURL(/\/runs\/[0-9a-f-]+/, { timeout: 10_000 });
    });

    test("shows validation error for invalid GitHub URL", async ({ page }) => {
        await page.goto("/");

        const urlInput = page.getByPlaceholder(/github.com/);
        await urlInput.fill("not-a-url");
        await page
            .locator(".launcher-card")
            .getByRole("button", { name: /Launch workflow/ })
            .click();

        await expect(page.getByText(/valid.*GitHub/)).toBeVisible({ timeout: 3000 });
        await expect(page.locator(".running-page")).not.toBeVisible();
    });

    test("can clear the URL input", async ({ page }) => {
        await page.goto("/");

        const urlInput = page.getByPlaceholder(/github.com/);
        await urlInput.fill("https://github.com/a/b");
        await expect(urlInput).toHaveValue("https://github.com/a/b");

        await page.getByRole("button", { name: "Clear URL" }).click();
        await expect(urlInput).toHaveValue("");
    });

    test("can switch launchers via the quick-select pills", async ({ page }) => {
        await page.goto("/");

        await page.getByText("Plan fix").click();

        const activePill = page.locator(".quick-workflows button.active");
        await expect(activePill).toContainText("Plan fix");
    });
});
