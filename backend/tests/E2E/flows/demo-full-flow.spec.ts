import { expect, test } from "@playwright/test";

/**
 * Demo-mode E2E: full visual flow from sign-in to viewing a report.
 *
 * Prerequisite: demo web server (VITE_DEMO_MODE build + `php artisan serve`).
 * Playwright starts this automatically via `scripts/e2e/serve-demo.sh`.
 *
 * Flow: navigate → paste URL → select launcher → launch → watch progress → view report.
 */
test.describe("Demo mode: sign-in → launch → report", () => {
    test("shows sign-in UI when clicking Sign in button", async ({ page }) => {
        await page.goto("/");

        // Click the "Sign in" button in the header.
        await page.getByRole("button", { name: "Sign in" }).click();

        // Verify the sign-in modal appears with email input.
        await expect(page.locator(".auth-card")).toBeVisible({ timeout: 5000 });
        await expect(page.getByPlaceholder(/you@example.com/)).toBeVisible();
        await expect(
            page.getByRole("button", { name: /Send sign-in link/ }),
        ).toBeVisible();

        // In demo mode, the sign-in form cannot actually submit (no backend
        // auth API), so we verify the UI renders and then navigate back to
        // the home page for subsequent tests.
        await page.goto("/");
        // We land back on the home page.
        await expect(page.locator("h1")).toContainText("in flow");
    });

    test("completes the full workflow from URL paste to report", async ({ page }) => {
        // 1. Navigate to the app.
        await page.goto("/");

        // 2. Verify the home page renders with launcher card.
        await expect(page.locator("h1")).toContainText("in flow");
        await expect(page.locator(".launcher-card")).toBeVisible();

        // 3. Paste a GitHub URL.
        const urlInput = page.getByPlaceholder(/github.com/);
        await urlInput.fill("https://github.com/laravel/framework/pull/42");
        await expect(urlInput).toHaveValue("https://github.com/laravel/framework/pull/42");

        // 4. Select the "review-pr" launcher (should already be selected by default).
        const activePill = page.locator(".quick-workflows button.active");
        await expect(activePill).toBeVisible();

        // 5. Launch the workflow (scoped to launcher-card to avoid
        //    ambiguous matches with workflow-grid cards).
        await page
            .locator(".launcher-card")
            .getByRole("button", { name: /Launch workflow/ })
            .click();

        // 6. Verify we transition to the running/demo-running view.
        await expect(page.locator(".running-page")).toBeVisible({ timeout: 5000 });

        // 7. Wait for all demo steps to complete (5 steps × ~780ms + 650ms delay ≈ 7s total).
        // The running view disappears and the report view appears.
        // The report renders demo findings.
        await expect(page.getByText("Missing authorization check")).toBeVisible({
            timeout: 15_000,
        });

        // 8. Verify the report shows structured findings with severity levels.
        await expect(page.getByText("high")).toBeVisible();
        await expect(page.getByText("Race condition")).toBeVisible();

        // 9. Verify the share URL is present on the report page.
        await expect(page.getByText(/Share/)).toBeVisible();
    });

    test("shows validation error for invalid GitHub URL", async ({ page }) => {
        await page.goto("/");

        // Type an invalid URL and try to launch (scoped to launcher-card).
        const urlInput = page.getByPlaceholder(/github.com/);
        await urlInput.fill("not-a-url");
        await page
            .locator(".launcher-card")
            .getByRole("button", { name: /Launch workflow/ })
            .click();

        // Should show validation error, not transition to running.
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

        // Quick-select pills show "Review PR" (review-pr), "Plan fix" (plan-issue),
        // "Explain" (explain-repository), "Laravel doctor" (laravel-doctor).
        await page.getByText("Plan fix").click();

        // The selected pill should now be active.
        const activePill = page.locator(".quick-workflows button.active");
        await expect(activePill).toContainText("Plan fix");
    });
});
