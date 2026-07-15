import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { RunHistory } from "../RunHistory.tsx";

/** Standard mock run object that satisfies decodeRun's validation. */
export const mockRun = (overrides: Record<string, unknown> = {}) => ({
    id: "run-1",
    launcher: "review-pr",
    input: { source_url: "https://github.com/a/b" },
    status: "failed",
    progress: ["Step 1", "Step 2"],
    result: null,
    error: null,
    started_at: "2026-01-01T00:00:00Z",
    completed_at: null,
    ...overrides,
});

// Mock the auth service so we don't hit the real API.
vi.mock("../../services/auth.ts", () => ({
    fetchUserRuns: vi.fn().mockResolvedValue({ data: [] }),
    retryRun: vi.fn().mockResolvedValue({ id: "new-run-id", status: "queued" }),
    deleteRun: vi.fn().mockResolvedValue(undefined),
}));

const navigate = vi.fn();

/** Create a deferred promise so we can resolve it cleanly after assertions. */
function deferred<T = void>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((r) => {
        resolve = r;
    });
    return { promise, resolve };
}

afterEach(() => {
    vi.clearAllMocks();
});

function renderComponent() {
    return render(<RunHistory navigate={navigate} />);
}

describe("RunHistory", () => {
    it("renders the status filter dropdown", async () => {
        renderComponent();
        expect(screen.getByRole("combobox")).toBeInTheDocument();
        expect(screen.getByText("All statuses")).toBeInTheDocument();
    });

    it("shows loading state initially", () => {
        renderComponent();
        expect(screen.getByText("Loading runs…")).toBeInTheDocument();
    });

    it("shows empty state when there are no runs", async () => {
        renderComponent();
        const runHistory = document.querySelector(".run-history") as HTMLElement;
        expect(
            await within(runHistory).findByText(/No runs in your history yet/),
        ).toBeInTheDocument();
    });

    it("shows error when fetch fails", async () => {
        const { fetchUserRuns } = await import("../../services/auth.ts");
        vi.mocked(fetchUserRuns).mockRejectedValueOnce(new Error("fail"));
        renderComponent();
        expect(await screen.findByText("fail")).toBeInTheDocument();
    });

    it("disables all action buttons while an action is in progress", async () => {
        const { fetchUserRuns } = await import("../../services/auth.ts");
        const user = userEvent.setup();

        vi.mocked(fetchUserRuns).mockResolvedValueOnce({
            data: [mockRun({ status: "failed" })],
        });

        renderComponent();

        const retryBtn = await screen.findByRole("button", { name: "Retry" });
        const deleteBtn = screen.getByRole("button", { name: "Delete" });
        const openBtn = screen.getByRole("button", { name: "Open" });

        await user.click(retryBtn);

        expect(retryBtn).toBeDisabled();
        expect(deleteBtn).toBeDisabled();
        expect(openBtn).toBeDisabled();
    });

    it("shows 'Retrying…' text on the retry button while pending", async () => {
        const { retryRun, fetchUserRuns } = await import("../../services/auth.ts");
        const user = userEvent.setup();

        vi.mocked(fetchUserRuns).mockResolvedValueOnce({
            data: [mockRun({ status: "failed" })],
        });

        const { promise, resolve } = deferred<{ id: string; status: string }>();
        vi.mocked(retryRun).mockReturnValueOnce(promise);

        renderComponent();
        const retryBtn = await screen.findByRole("button", { name: "Retry" });

        await user.click(retryBtn);

        expect(screen.getByRole("button", { name: "Retrying…" })).toBeInTheDocument();

        resolve({ id: "ok", status: "ok" });
    });

    it("shows 'Deleting…' text on the delete button while pending", async () => {
        const { deleteRun, fetchUserRuns } = await import("../../services/auth.ts");
        const user = userEvent.setup();

        vi.mocked(fetchUserRuns).mockResolvedValueOnce({
            data: [mockRun({ status: "completed" })],
        });

        const { promise, resolve } = deferred();
        vi.mocked(deleteRun).mockReturnValueOnce(promise);

        vi.spyOn(window, "confirm").mockReturnValue(true);

        renderComponent();
        const deleteBtn = await screen.findByRole("button", { name: "Delete" });

        await user.click(deleteBtn);

        expect(screen.getByRole("button", { name: "Deleting…" })).toBeInTheDocument();

        resolve(undefined);
    });

    it("does not show retry button for running runs", async () => {
        const { fetchUserRuns } = await import("../../services/auth.ts");

        vi.mocked(fetchUserRuns).mockResolvedValueOnce({
            data: [mockRun({ status: "running" })],
        });

        renderComponent();
        await screen.findByText("running", { selector: ".status-badge" });

        expect(screen.queryByRole("button", { name: "Retry" })).not.toBeInTheDocument();
        expect(screen.getByRole("button", { name: "Delete" })).toBeInTheDocument();
        expect(screen.getByRole("button", { name: "Open" })).toBeInTheDocument();
    });

    it("navigates to the run page when Open is clicked", async () => {
        const { fetchUserRuns } = await import("../../services/auth.ts");
        const user = userEvent.setup();
        const pushState = vi.spyOn(window.history, "pushState");

        vi.mocked(fetchUserRuns).mockResolvedValueOnce({
            data: [
                {
                    id: "run-abc",
                    launcher: "review-pr",
                    input: { source_url: "https://github.com/a/b" },
                    status: "completed",
                    progress: [],
                    result: null,
                    error: null,
                    started_at: "2026-01-01T00:00:00Z",
                    completed_at: null,
                },
            ],
        });

        renderComponent();
        const openBtn = await screen.findByRole("button", { name: "Open" });

        await user.click(openBtn);

        expect(pushState).toHaveBeenCalledWith({}, "", "/runs/run-abc");
        expect(navigate).toHaveBeenCalledWith("/runs/run-abc");
    });
});
