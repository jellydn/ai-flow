import { expect, test } from "@playwright/test";
import { postAuthJson } from "../helpers/csrf.ts";

/**
 * E2E for all 4 launcher flows (review-pr, plan-issue, explain-repository,
 * laravel-doctor) against the real Laravel backend.
 *
 * Server: `scripts/e2e/serve-real.sh` (SQLite, migrate --seed, sync queue,
 * RUNS_RATE_LIMIT_PER_HOUR=100 so all launchers can POST in one pass).
 *
 * Without OPENAI_API_KEY on the server, guest POST /api/runs returns 422
 * (no provider key available). With a key, it returns 202 + a pollable run.
 * Authenticated users supply a one-time provider key in the request body.
 */
const LAUNCHERS = [
    {
        slug: "review-pr",
        name: "Review Pull Request",
        sampleUrl: "https://github.com/laravel/framework/pull/1",
        inputType: "pull_request",
    },
    {
        slug: "plan-issue",
        name: "Plan GitHub Issue",
        sampleUrl: "https://github.com/laravel/framework/issues/1",
        inputType: "issue",
    },
    {
        slug: "explain-repository",
        name: "Explain Repository",
        sampleUrl: "https://github.com/laravel/framework",
        inputType: "repository",
    },
    {
        slug: "laravel-doctor",
        name: "Laravel Project Doctor",
        sampleUrl: "https://github.com/laravel/framework",
        inputType: "repository",
    },
] as const;

test.describe("All launcher flows (real backend)", () => {
    test("GET /api/launchers returns all 4 seeded launchers", async ({ request }) => {
        const res = await request.get("/api/launchers");
        expect(res.status()).toBe(200);

        const body = await res.json();
        // /api/launchers returns a flat array (not wrapped in {data: [...]}).
        expect(Array.isArray(body)).toBe(true);
        expect(body.length).toBeGreaterThanOrEqual(4);

        const slugs = body.map((l: { slug: string }) => l.slug);
        for (const launcher of LAUNCHERS) {
            expect(slugs, `missing launcher: ${launcher.slug}`).toContain(launcher.slug);
        }

        // Each launcher has the required fields.
        for (const launcher of body) {
            expect(launcher).toHaveProperty("id");
            expect(launcher).toHaveProperty("slug");
            expect(launcher).toHaveProperty("name");
            expect(launcher).toHaveProperty("description");
        }
    });

    for (const launcher of LAUNCHERS) {
        test(`POST /api/runs accepts ${launcher.slug} with valid URL (guest)`, async ({
            request,
        }) => {
            const res = await postAuthJson(request, "/api/runs", {
                launcher: launcher.slug,
                source_url: launcher.sampleUrl,
            });

            // Without a server AI key, guest runs get 422 (no provider key).
            // With a key, they get 202 + queued run.
            const status = res.status();
            expect([202, 422], `${launcher.slug}: unexpected status ${status}`).toContain(
                status,
            );

            if (status === 202) {
                const body = await res.json();
                expect(body.status).toBe("queued");
                expect(typeof body.id).toBe("string");
            }
        });

        test(`POST /api/runs rejects ${launcher.slug} with invalid URL`, async ({
            request,
        }) => {
            const res = await postAuthJson(request, "/api/runs", {
                launcher: launcher.slug,
                source_url: "not-a-url",
            });
            expect(res.status()).toBe(422);

            const body = await res.json();
            expect(body).toHaveProperty("errors");
        });

        test(`POST /api/runs accepts ${launcher.slug} with cross-input-type URL`, async ({
            request,
        }) => {
            // URL validation only checks https://github.com/ format, not the
            // input_type. A repo URL for review-pr or a PR URL for explain-repository
            // passes URL validation. It may fail later in the job (input-type
            // mismatch), but the API accepts it.
            const crossUrl =
                launcher.inputType === "pull_request"
                    ? "https://github.com/laravel/framework"
                    : "https://github.com/laravel/framework/pull/99";

            const res = await postAuthJson(request, "/api/runs", {
                launcher: launcher.slug,
                source_url: crossUrl,
            });

            // 202 (with key) or 422 (no key) — URL format is valid either way.
            const status = res.status();
            expect([202, 422], `${launcher.slug}: unexpected status ${status}`).toContain(
                status,
            );
        });
    }

    test("home page renders all 4 launcher workflow cards", async ({ page }) => {
        await page.goto("/");

        await expect(page.locator("h1")).toContainText("in flow", { timeout: 10_000 });

        // The workflow-grid renders one .workflow-card button per active launcher.
        const cards = page.locator(".workflow-card");
        await expect(cards).toHaveCount(4, { timeout: 10_000 });

        // Each launcher should be visible in the workflow grid.
        for (const launcher of LAUNCHERS) {
            await expect(
                page.locator(".workflow-card").filter({
                    hasText: launcher.name,
                }),
            ).toBeVisible();
        }
    });

    test("can switch between all 4 launchers via quick-select pills", async ({ page }) => {
        await page.goto("/");

        const pills = page.locator(".quick-workflows button");
        await expect(pills).toHaveCount(4, { timeout: 10_000 });

        // Click each pill and verify it becomes active.
        const pillLabels: Record<string, string> = {
            "review-pr": "Review PR",
            "plan-issue": "Plan fix",
            "explain-repository": "Explain",
            "laravel-doctor": "Laravel doctor",
        };

        for (const launcher of LAUNCHERS) {
            const label = pillLabels[launcher.slug];
            await page.getByText(label, { exact: true }).click();

            const activePill = page.locator(".quick-workflows button.active");
            await expect(activePill).toContainText(label);
        }
    });
});
