import type { Locator, Page } from "@playwright/test";

export function authCard(page: Page): Locator {
    return page.locator(".auth-card");
}

export function authTabPanel(page: Page, tabName: "Password" | "Sign up" | "Email link"): Locator {
    return authCard(page).getByRole("tabpanel", { name: tabName });
}
