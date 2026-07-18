import { expect, test } from "@playwright/test";
import { authCard, authTabPanel } from "../helpers/authCard.ts";
import { postAuthJson, putAuthJson } from "../helpers/csrf.ts";
import { E2E_PASSWORD, uniqueEmail } from "../helpers/uniqueEmail.ts";

/**
 * Per-user workflow prompt overrides (#59) against real backend.
 *
 * Server: `scripts/e2e/serve-real.sh` (PLAYWRIGHT_WEB_SERVER=real).
 */
test.describe.configure({ mode: "serial" });

test.describe("Launcher prompts (real backend)", () => {
    async function registerAndOpenApiKeys(page: import("@playwright/test").Page, email: string) {
        const password = E2E_PASSWORD;

        await page.goto("/");
        await page.getByRole("button", { name: "Sign in" }).click();
        await expect(authCard(page)).toBeVisible();

        const signUp = authTabPanel(page, "Sign up");
        await authCard(page).getByRole("tab", { name: "Sign up" }).click();
        await signUp.getByLabel("Email").fill(email);
        await signUp.getByLabel("Password", { exact: true }).fill(password);
        await signUp.getByLabel("Confirm password").fill(password);
        await signUp.getByRole("button", { name: "Create account" }).click();

        await expect(page).toHaveURL(/\/user\/?$/, { timeout: 15_000 });
        await page.getByRole("tab", { name: "API Keys" }).click();
        await expect(page.getByRole("heading", { name: "Workflow prompts" })).toBeVisible({
            timeout: 15_000,
        });
    }

    test("API Keys shows four workflow prompts with defaults", async ({ page }) => {
        await registerAndOpenApiKeys(page, uniqueEmail("prompts-list"));

        const section = page.locator(".workflow-prompts");
        await expect(section.getByRole("heading", { name: "Workflow prompts" })).toBeVisible();
        await expect(section.locator(".workflow-prompt-card")).toHaveCount(4);
        await expect(section.locator(".workflow-prompt-badge")).toHaveCount(0);

        await expect(
            section.getByRole("textbox", { name: /Prompt for Laravel Project Doctor/i }),
        ).toBeVisible();
    });

    test("save custom prompt shows Custom badge and reset restores default", async ({ page }) => {
        await registerAndOpenApiKeys(page, uniqueEmail("prompts-save"));

        const custom = trimRepeat("E2E custom Laravel doctor workflow prompt. ", 3);
        const textarea = page.getByRole("textbox", {
            name: /Prompt for Laravel Project Doctor/i,
        });
        await textarea.fill(custom);

        const card = page.locator(".workflow-prompt-card").filter({
            has: page.getByText("Laravel Project Doctor", { exact: true }),
        });
        await card.getByRole("button", { name: "Save" }).click();
        await expect(card.locator(".workflow-prompt-badge")).toBeVisible({ timeout: 10_000 });

        page.once("dialog", (dialog) => dialog.accept());
        await card.getByRole("button", { name: "Reset to default" }).click();
        await expect(card.locator(".workflow-prompt-badge")).toHaveCount(0, { timeout: 10_000 });
    });

    test("authenticated API saves prompt override and accepts run create", async ({ request }) => {
        const email = uniqueEmail("prompts-run");
        const password = E2E_PASSWORD;
        const custom = trimRepeat("E2E snapshot prompt for explain-repository run. ", 2);

        const registerRes = await postAuthJson(request, "/auth/register", {
            email,
            password,
            password_confirmation: password,
        });
        expect(registerRes.status()).toBe(201);

        const putRes = await putAuthJson(request, "/api/user/launcher-prompts/explain-repository", {
            prompt_template: custom,
        });
        expect(putRes.status()).toBe(200);

        const listRes = await request.get("/api/user/launcher-prompts", {
            headers: { Accept: "application/json" },
        });
        expect(listRes.ok()).toBeTruthy();
        const listBody = (await listRes.json()) as {
            data: { slug: string; uses_override: boolean; override_prompt_template: string }[];
        };
        const explain = listBody.data.find((row) => row.slug === "explain-repository");
        expect(explain?.uses_override).toBe(true);
        expect(explain?.override_prompt_template).toBe(custom);

        const runRes = await postAuthJson(request, "/api/runs", {
            launcher: "explain-repository",
            source_url: "https://github.com/laravel/framework",
            provider: { id: "openai", api_key: "sk-e2e-placeholder-not-for-real-openai" },
        });
        expect(runRes.status()).toBe(202);
        const runBody = (await runRes.json()) as { id: string; status: string };
        expect(runBody.id).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
        );
        expect(runBody.status).toBe("queued");
        // DB prompt_snapshot assertion: RunPromptSnapshotTest (PHPUnit).
    });
});

function trimRepeat(fragment: string, times: number): string {
    return fragment.repeat(times).trim();
}
