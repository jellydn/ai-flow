import { expect, test } from "@playwright/test";
import { authCard, authTabPanel } from "../helpers/authCard.ts";
import { postAuthJson } from "../helpers/csrf.ts";
import { E2E_PASSWORD, uniqueEmail } from "../helpers/uniqueEmail.ts";

/**
 * Real-backend auth user flows (password + email-link request).
 *
 * Server: `scripts/e2e/serve-real.sh` (SQLite, migrate --seed, sync queue).
 */
test.describe("Auth user flow (real backend)", () => {
    test("register with password → dashboard → logout → sign in again", async ({ page }) => {
        const email = uniqueEmail("register");
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

        await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.locator(".user-email")).toContainText(email);

        await page.getByRole("button", { name: "Sign out" }).click();
        await expect(page.locator("h1")).toContainText("in flow", { timeout: 10_000 });
        await expect(page.getByRole("button", { name: "Sign in" })).toBeVisible();

        await page.getByRole("button", { name: "Sign in" }).click();
        const signIn = authTabPanel(page, "Password");
        await authCard(page).getByRole("tab", { name: "Password" }).click();
        await signIn.getByLabel("Email").fill(email);
        await signIn.getByLabel("Password").fill(password);
        await signIn.getByRole("button", { name: "Sign in", exact: true }).click();

        await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible({
            timeout: 15_000,
        });
    });

    test("password sign-in shows error for wrong password", async ({ page, request }) => {
        const email = uniqueEmail("login-fail");
        const password = E2E_PASSWORD;

        const registerRes = await postAuthJson(request, "/auth/register", {
            email,
            password,
            password_confirmation: password,
        });
        expect(registerRes.status()).toBe(201);

        await page.goto("/");
        await page.getByRole("button", { name: "Sign in" }).click();

        const signIn = authTabPanel(page, "Password");
        await signIn.getByLabel("Email").fill(email);
        await signIn.getByLabel("Password").fill("WrongPass1!");
        await signIn.getByRole("button", { name: "Sign in", exact: true }).click();

        await expect(authCard(page).locator(".auth-error")).toBeVisible({ timeout: 10_000 });
        await expect(page.getByRole("heading", { name: "Dashboard" })).not.toBeVisible();
    });

    test("sign-up rejects duplicate email", async ({ page, request }) => {
        const email = uniqueEmail("dup");
        const password = E2E_PASSWORD;

        const first = await postAuthJson(request, "/auth/register", {
            email,
            password,
            password_confirmation: password,
        });
        expect(first.status()).toBe(201);

        await page.goto("/");
        await page.getByRole("button", { name: "Sign in" }).click();

        const signUp = authTabPanel(page, "Sign up");
        await authCard(page).getByRole("tab", { name: "Sign up" }).click();
        await signUp.getByLabel("Email").fill(email);
        await signUp.getByLabel("Password", { exact: true }).fill(password);
        await signUp.getByLabel("Confirm password").fill(password);
        await signUp.getByRole("button", { name: "Create account" }).click();

        await expect(authCard(page).locator(".auth-error")).toBeVisible({ timeout: 10_000 });
        await expect(page.getByRole("heading", { name: "Dashboard" })).not.toBeVisible();
    });

    test("email link tab submits and shows check-your-email screen", async ({ page }) => {
        const email = uniqueEmail("magic");

        await page.goto("/");
        await page.getByRole("button", { name: "Sign in" }).click();

        const magic = authTabPanel(page, "Email link");
        await authCard(page).getByRole("tab", { name: "Email link" }).click();
        await magic.getByLabel("Email").fill(email);
        await magic.getByRole("button", { name: /Send sign-in link/ }).click();

        await expect(page.getByRole("heading", { name: "Check your email" })).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByText(email)).toBeVisible();

        await page.getByRole("button", { name: "Back to sign in" }).click();
        await page.getByRole("button", { name: "Sign in" }).click();
        await expect(authCard(page)).toBeVisible();
        await expect(authCard(page).getByRole("tab", { name: "Password" })).toBeVisible();
    });

    test("auth tabs: switch sign-up ↔ password without losing email field", async ({ page }) => {
        await page.goto("/");
        await page.getByRole("button", { name: "Sign in" }).click();

        const card = authCard(page);
        await card.getByRole("tab", { name: "Sign up" }).click();
        await authTabPanel(page, "Sign up").getByLabel("Email").fill("tabs@e2e.ai-flow.test");

        await card.getByRole("tab", { name: "Password" }).click();
        await expect(authTabPanel(page, "Password").getByLabel("Email")).toHaveValue(
            "tabs@e2e.ai-flow.test",
        );

        await authTabPanel(page, "Password").getByRole("button", { name: "Use email link" }).click();
        await expect(card.getByRole("tab", { name: "Email link" })).toHaveAttribute(
            "aria-selected",
            "true",
        );
    });
});
