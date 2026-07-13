import type { ComponentProps } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { UrlInput } from "../UrlInput.tsx";
import { LauncherSelector } from "../LauncherSelector.tsx";
import { LaunchArea } from "../LaunchArea.tsx";

// ---------------------------------------------------------------------------
// UrlInput
// ---------------------------------------------------------------------------
describe("UrlInput", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it("renders the input with placeholder", () => {
        render(
            <UrlInput
                url=""
                setUrl={vi.fn()}
                error=""
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );
        expect(screen.getByPlaceholderText(/github.com/)).toBeInTheDocument();
    });

    it("calls setUrl and clears error on input change", async () => {
        const setUrl = vi.fn();
        const setError = vi.fn();
        render(
            <UrlInput
                url=""
                setUrl={setUrl}
                error="old-error"
                setError={setError}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );

        await userEvent.setup().type(screen.getByRole("textbox"), "x");
        expect(setUrl).toHaveBeenCalled();
        expect(setError).toHaveBeenCalledWith("");
    });

    it("calls launch on Enter key when not launching", async () => {
        const launch = vi.fn();
        render(
            <UrlInput
                url="https://github.com/a/b"
                setUrl={vi.fn()}
                error=""
                setError={vi.fn()}
                launch={launch}
                isLaunching={false}
            />,
        );

        await userEvent.setup().type(screen.getByRole("textbox"), "{Enter}");
        expect(launch).toHaveBeenCalled();
    });

    it("does not call launch on Enter when isLaunching is true", async () => {
        const launch = vi.fn();
        render(
            <UrlInput
                url="https://github.com/a/b"
                setUrl={vi.fn()}
                error=""
                setError={vi.fn()}
                launch={launch}
                isLaunching={true}
            />,
        );

        await userEvent.setup().type(screen.getByRole("textbox"), "{Enter}");
        expect(launch).not.toHaveBeenCalled();
    });

    it("shows clear button when url is non-empty", () => {
        render(
            <UrlInput
                url="https://github.com/a/b"
                setUrl={vi.fn()}
                error=""
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );
        expect(screen.getByRole("button", { name: "Clear URL" })).toBeInTheDocument();
    });

    it("does not show clear button when url is empty", () => {
        render(
            <UrlInput
                url=""
                setUrl={vi.fn()}
                error=""
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );
        expect(screen.queryByRole("button", { name: "Clear URL" })).not.toBeInTheDocument();
    });

    it("clear button still works when isLaunching is true", async () => {
        const setUrl = vi.fn();
        render(
            <UrlInput
                url="https://github.com/a/b"
                setUrl={setUrl}
                error=""
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={true}
            />,
        );

        await userEvent.setup().click(screen.getByRole("button", { name: "Clear URL" }));
        expect(setUrl).toHaveBeenCalledWith("");
    });

    it("calls setUrl with empty string on clear click", async () => {
        const setUrl = vi.fn();
        render(
            <UrlInput
                url="https://github.com/a/b"
                setUrl={setUrl}
                error=""
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );

        await userEvent.setup().click(screen.getByRole("button", { name: "Clear URL" }));
        expect(setUrl).toHaveBeenCalledWith("");
    });

    it("shows error message when error prop is set", () => {
        render(
            <UrlInput
                url=""
                setUrl={vi.fn()}
                error="Invalid URL"
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );
        expect(screen.getByText("Invalid URL")).toBeInTheDocument();
    });

    it("does not show error message when error prop is empty", () => {
        render(
            <UrlInput
                url=""
                setUrl={vi.fn()}
                error=""
                setError={vi.fn()}
                launch={vi.fn()}
                isLaunching={false}
            />,
        );
        expect(screen.queryByText("Invalid URL")).not.toBeInTheDocument();
    });
});

// ---------------------------------------------------------------------------
// LauncherSelector
// ---------------------------------------------------------------------------
describe("LauncherSelector", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    const mockLaunchers = [
        {
            id: "1",
            slug: "review-pr",
            name: "Review PR",
            description: "…",
            input_type: "pull-request",
        },
        {
            id: "2",
            slug: "plan-issue",
            name: "Plan Issue",
            description: "…",
            input_type: "issue",
        },
        {
            id: "3",
            slug: "explain-repository",
            name: "Explain Repo",
            description: "…",
            input_type: "repository",
        },
        {
            id: "4",
            slug: "laravel-doctor",
            name: "Laravel Doctor",
            description: "…",
            input_type: "repository",
        },
        {
            id: "5",
            slug: "extra",
            name: "Extra",
            description: "…",
            input_type: "repo",
        },
    ];

    it("renders at most 4 launcher buttons even when 5 are provided", () => {
        render(
            <LauncherSelector
                launchers={mockLaunchers}
                selected="review-pr"
                setSelected={vi.fn()}
            />,
        );
        const buttons = screen.getAllByRole("button");
        expect(buttons).toHaveLength(4);
        // The 5th launcher should not be rendered.
        expect(screen.queryByText("Extra")).not.toBeInTheDocument();
    });

    it("marks the selected launcher as active", () => {
        render(
            <LauncherSelector
                launchers={mockLaunchers}
                selected="plan-issue"
                setSelected={vi.fn()}
            />,
        );
        // quickLabel("plan-issue") returns "Plan fix"
        const activeBtn = screen.getByText("Plan fix").closest("button");
        expect(activeBtn?.className).toContain("active");
    });

    it("non-selected button does not have active class", () => {
        render(
            <LauncherSelector
                launchers={mockLaunchers}
                selected="review-pr"
                setSelected={vi.fn()}
            />,
        );
        const inactiveBtn = screen.getByText("Plan fix").closest("button");
        expect(inactiveBtn?.className).not.toContain("active");
    });

    it("calls setSelected on click", async () => {
        const setSelected = vi.fn();
        render(
            <LauncherSelector
                launchers={mockLaunchers}
                selected="review-pr"
                setSelected={setSelected}
            />,
        );

        await userEvent.setup().click(screen.getByText("Plan fix"));
        expect(setSelected).toHaveBeenCalledWith("plan-issue");
    });

    it("falls back to launcher.name when no meta found", () => {
        // quickLabel falls back to title when slug is unknown
        const noMetaLauncher = [
            {
                id: "99",
                slug: "unknown-workflow",
                name: "Unknown Workflow",
                description: "…",
                input_type: "repo",
            },
        ];
        render(
            <LauncherSelector
                launchers={noMetaLauncher}
                selected="review-pr"
                setSelected={vi.fn()}
            />,
        );
        // The component uses launcher.name as a last resort
        expect(screen.getByText("Unknown Workflow")).toBeInTheDocument();
    });
});

// ---------------------------------------------------------------------------
// LaunchArea
// ---------------------------------------------------------------------------
function renderLaunchArea(overrides: Partial<ComponentProps<typeof LaunchArea>> = {}) {
    return render(
        <LaunchArea
            selectedTool="codex"
            setSelectedTool={vi.fn()}
            apiKey=""
            setApiKey={vi.fn()}
            launch={vi.fn()}
            isLaunching={false}
            {...overrides}
        />,
    );
}

describe("LaunchArea", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it("renders launch button with 'Launch workflow' text", () => {
        renderLaunchArea();
        expect(screen.getByRole("button", { name: /Launch workflow/ })).toBeInTheDocument();
    });

    it("shows 'Starting…' and disables button when isLaunching", () => {
        renderLaunchArea({ isLaunching: true });
        const btn = screen.getByRole("button", { name: "Starting…" });
        expect(btn).toBeDisabled();
    });

    it("calls launch on button click", async () => {
        const launch = vi.fn();
        renderLaunchArea({ launch });

        await userEvent.setup().click(screen.getByRole("button", { name: /Launch workflow/ }));
        expect(launch).toHaveBeenCalled();
    });

    it("disabled button does not fire launch on click", async () => {
        const launch = vi.fn();
        renderLaunchArea({ launch, isLaunching: true });

        await userEvent.setup().click(screen.getByRole("button", { name: "Starting…" }));
        expect(launch).not.toHaveBeenCalled();
    });

    it("renders API key input with placeholder", () => {
        renderLaunchArea();
        expect(screen.getByPlaceholderText(/Leave blank/)).toBeInTheDocument();
    });

    it("renders AI tools from ai-launcher config", () => {
        renderLaunchArea();
        expect(screen.getByRole("combobox", { name: "AI tool" })).toHaveValue("codex");
        expect(screen.getByText("OpenAI Codex CLI")).toBeInTheDocument();
    });

    it("calls setApiKey on input change", async () => {
        const setApiKey = vi.fn();
        renderLaunchArea({ setApiKey });

        await userEvent.setup().type(screen.getByPlaceholderText(/Leave blank/), "sk-123");
        expect(setApiKey).toHaveBeenCalled();
    });

    it("renders trust row with repo and timing info", () => {
        renderLaunchArea();
        expect(screen.getByText(/Public repositories only/)).toBeInTheDocument();
        expect(screen.getByText(/Results in under a minute/)).toBeInTheDocument();
    });
});
