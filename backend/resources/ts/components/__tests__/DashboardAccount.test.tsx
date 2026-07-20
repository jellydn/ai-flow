import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Dashboard } from "../Dashboard.tsx";
import type { User } from "../../services/auth.ts";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
vi.mock("../../services/auth.ts", async (importActual) => {
    const actual = await importActual<typeof import("../../services/auth.ts")>();
    return {
        ...actual,
        deleteAccount: vi.fn(),
        logout: vi.fn(),
    };
});

import { deleteAccount, logout } from "../../services/auth.ts";

const mockUser: User = {
    id: 1,
    name: "Test User",
    email: "test@example.com",
    email_verified_at: null,
    last_login_at: null,
};

const baseProps = {
    user: mockUser,
    onLogout: vi.fn(),
    navigate: vi.fn(),
};

// ---------------------------------------------------------------------------
// Dashboard — Account tab + deletion flow
// ---------------------------------------------------------------------------
describe("Dashboard — Account tab", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it("does not render account settings on default tab (history)", async () => {
        render(<Dashboard {...baseProps} />);
        expect(screen.queryByText("Delete account")).not.toBeInTheDocument();
        expect(screen.queryByText("Privacy & Data")).not.toBeInTheDocument();
        // Dashboard renders RunHistory on the history tab; its useEffect fires
        // an async fetchUserRuns that resolves after this synchronous test
        // returns. Flush it inside act() to avoid the warning.
        await waitFor(() => {});
    });

    it("renders account tab button", async () => {
        render(<Dashboard {...baseProps} />);
        expect(screen.getByRole("tab", { name: "Account" })).toBeInTheDocument();
        await waitFor(() => {});
    });

    it("switches to account tab on click and shows privacy panel", async () => {
        render(<Dashboard {...baseProps} />);

        await userEvent.setup().click(screen.getByRole("tab", { name: "Account" }));

        expect(screen.getByText("Privacy & Data")).toBeInTheDocument();
        expect(screen.getByText(/encrypted before being stored/)).toBeInTheDocument();
        expect(screen.getByText(/GitHub repository or issue content/)).toBeInTheDocument();
    });

    it("shows danger zone with delete account heading after switching to account tab", async () => {
        render(<Dashboard {...baseProps} />);

        await userEvent.setup().click(screen.getByRole("tab", { name: "Account" }));

        expect(screen.getByText("Delete account")).toBeInTheDocument();
        expect(screen.getByText(/Permanently delete your account/)).toBeInTheDocument();
        expect(screen.getByText(/This action cannot be undone/)).toBeInTheDocument();
    });
});

describe("Dashboard — Account deletion confirmation flow", () => {
    afterEach(() => {
        vi.clearAllMocks();
        vi.mocked(deleteAccount).mockReset();
        vi.mocked(logout).mockReset();
    });

    // Helper: switch to account tab.
    async function goToAccountTab() {
        render(<Dashboard {...baseProps} />);
        await userEvent.setup().click(screen.getByRole("tab", { name: "Account" }));
    }

    it("renders confirmation checkbox and delete button", async () => {
        await goToAccountTab();

        expect(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        ).toBeInTheDocument();
        expect(screen.getByRole("button", { name: "Delete my account" })).toBeInTheDocument();
    });

    it("delete button is disabled when checkbox is not checked", async () => {
        await goToAccountTab();

        const deleteBtn = screen.getByRole("button", { name: "Delete my account" });
        expect(deleteBtn).toBeDisabled();
    });

    it("delete button is enabled when checkbox is checked", async () => {
        await goToAccountTab();

        await userEvent.setup().click(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        );

        const deleteBtn = screen.getByRole("button", { name: "Delete my account" });
        expect(deleteBtn).toBeEnabled();
    });

    it("calls deleteAccount and onLogout when delete is confirmed and succeeds", async () => {
        vi.mocked(deleteAccount).mockResolvedValue(undefined);
        await goToAccountTab();

        const user = userEvent.setup();
        await user.click(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        );
        await user.click(screen.getByRole("button", { name: "Delete my account" }));

        expect(deleteAccount).toHaveBeenCalledOnce();
        expect(baseProps.onLogout).toHaveBeenCalled();
    });

    it("shows 'Deleting…' and disables button while deletion is in progress", async () => {
        // Never resolves — keeps promise pending.
        vi.mocked(deleteAccount).mockReturnValue(new Promise(() => {}));
        await goToAccountTab();

        const user = userEvent.setup();
        await user.click(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        );
        await user.click(screen.getByRole("button", { name: "Delete my account" }));

        const btn = screen.getByRole("button", { name: "Deleting…" });
        expect(btn).toBeDisabled();
    });

    it("shows error message when deleteAccount throws", async () => {
        vi.mocked(deleteAccount).mockRejectedValue(new Error("Server error 500"));
        await goToAccountTab();

        const user = userEvent.setup();
        await user.click(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        );
        await user.click(screen.getByRole("button", { name: "Delete my account" }));

        expect(await screen.findByText("Server error 500")).toBeInTheDocument();
        expect(baseProps.onLogout).not.toHaveBeenCalled();
    });

    it("shows generic error for non-Error thrown values", async () => {
        vi.mocked(deleteAccount).mockRejectedValue("string error");
        await goToAccountTab();

        const user = userEvent.setup();
        await user.click(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        );
        await user.click(screen.getByRole("button", { name: "Delete my account" }));

        expect(await screen.findByText("Failed to delete account.")).toBeInTheDocument();
    });

    it("re-enables delete button after an error", async () => {
        vi.mocked(deleteAccount).mockRejectedValue(new Error("Failed"));
        await goToAccountTab();

        const user = userEvent.setup();
        await user.click(
            screen.getByRole("checkbox", {
                name: /I understand this action is permanent/,
            }),
        );
        await user.click(screen.getByRole("button", { name: "Delete my account" }));

        // Wait for the error to appear, then the button should be re-enabled.
        await screen.findByText("Failed");
        const btn = screen.getByRole("button", { name: "Delete my account" });
        expect(btn).toBeEnabled();
    });

    it("does not call deleteAccount when checkbox is unchecked and button is clicked", async () => {
        // Even though the button is disabled, ensure clicking it does nothing.
        vi.mocked(deleteAccount).mockResolvedValue(undefined);
        await goToAccountTab();

        // Button should be disabled, so userEvent.click won't actually fire the onClick handler.
        const btn = screen.getByRole("button", { name: "Delete my account" });
        expect(btn).toBeDisabled();

        await userEvent.setup().click(btn);
        expect(deleteAccount).not.toHaveBeenCalled();
    });

    it("unchecking the checkbox re-disables the delete button", async () => {
        await goToAccountTab();

        const user = userEvent.setup();
        const checkbox = screen.getByRole("checkbox", {
            name: /I understand this action is permanent/,
        });

        // Check → enables
        await user.click(checkbox);
        expect(screen.getByRole("button", { name: "Delete my account" })).toBeEnabled();

        // Uncheck → disables
        await user.click(checkbox);
        expect(screen.getByRole("button", { name: "Delete my account" })).toBeDisabled();
    });
});

describe("Dashboard — Logout", () => {
    afterEach(() => {
        vi.clearAllMocks();
        vi.mocked(logout).mockReset();
    });

    it("renders sign out button on default tab", () => {
        render(<Dashboard {...baseProps} />);
        expect(screen.getByRole("button", { name: "Sign out" })).toBeInTheDocument();
    });

    it("calls logout and onLogout when sign out is clicked", async () => {
        vi.mocked(logout).mockResolvedValue(undefined);
        render(<Dashboard {...baseProps} />);

        await userEvent.setup().click(screen.getByRole("button", { name: "Sign out" }));

        expect(logout).toHaveBeenCalledOnce();
        expect(baseProps.onLogout).toHaveBeenCalled();
    });

    it("shows 'Signing out…' and disables button while logout is pending", async () => {
        vi.mocked(logout).mockReturnValue(new Promise(() => {}));
        render(<Dashboard {...baseProps} />);

        await userEvent.setup().click(screen.getByRole("button", { name: "Sign out" }));

        const btn = screen.getByRole("button", { name: "Signing out…" });
        expect(btn).toBeDisabled();
    });

    it("calls onLogout even if logout() throws", async () => {
        vi.mocked(logout).mockRejectedValue(new Error("Network error"));
        render(<Dashboard {...baseProps} />);

        await userEvent.setup().click(screen.getByRole("button", { name: "Sign out" }));

        // The catch+finally in handleLogout ensures onLogout is always called.
        expect(baseProps.onLogout).toHaveBeenCalled();
    });
});
