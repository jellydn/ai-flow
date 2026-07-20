import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AppViews } from "../AppViews.tsx";
import type { RunProviderId } from "../../services/run.ts";

// Mock heavy dependencies so we can test view routing in isolation.
vi.mock("../Dashboard.tsx", () => ({
    Dashboard: ({ user, onLogout }: { user: { email: string }; onLogout: () => void }) => (
        <div data-testid="dashboard">
            <span>{user.email}</span>
            <button type="button" onClick={onLogout}>
                Log out
            </button>
        </div>
    ),
}));

vi.mock("../Home.tsx", () => ({
    Home: (props: Record<string, unknown>) => (
        <div data-testid="home">Home — {props.selected as string}</div>
    ),
}));

vi.mock("../Report.tsx", () => ({
    Report: ({ runId }: { runId: string | null }) => (
        <div data-testid="report">Report — {runId ?? "none"}</div>
    ),
}));

vi.mock("../Running.tsx", () => ({
    Running: ({
        title,
        repo,
        currentStep,
    }: {
        title: string;
        repo: string;
        currentStep: number;
    }) => (
        <div data-testid="running">
            {title} / {repo} / step {currentStep}
        </div>
    ),
}));

vi.mock("../SignIn.tsx", () => ({
    SignIn: ({
        onRequested,
        onAuthenticated,
    }: {
        onRequested: (email: string) => void;
        onAuthenticated: (user: { email: string }) => void;
    }) => (
        <div data-testid="sign-in">
            <button type="button" onClick={() => onRequested("a@b.com")}>
                Send link
            </button>
            <button
                type="button"
                onClick={() =>
                    onAuthenticated({
                        id: 1,
                        email: "pw@b.com",
                        name: null,
                        email_verified_at: null,
                        last_login_at: null,
                    } as import("../../services/auth.ts").User)
                }
            >
                Password sign-in
            </button>
        </div>
    ),
}));

const baseAuthState = {
    user: null,
    checked: false,
    deepLinkLoading: false,
};

// Use explicit generic types so vi.fn() matches AuthActions function signatures.
let onRequested: ReturnType<typeof vi.fn<(email: string) => void>>;
let onAuthenticated: ReturnType<
    typeof vi.fn<
        (user: {
            id: number;
            email: string;
            name: string | null;
            email_verified_at: string | null;
            last_login_at: string | null;
        }) => void
    >
>;
let onLogout: ReturnType<typeof vi.fn<() => void>>;

beforeEach(() => {
    window.history.replaceState({}, "", "/");
    onRequested = vi.fn();
    onAuthenticated = vi.fn();
    onLogout = vi.fn();
});

const baseHomeProps = {
    selected: "review-pr",
    setSelected: vi.fn(),
    url: "",
    setUrl: vi.fn(),
    error: "",
    setError: vi.fn(),
    launch: vi.fn(),
    isLaunching: false,
    apiKey: "",
    setApiKey: vi.fn(),
    selectedProvider: "openai" as RunProviderId,
    setSelectedProvider: vi.fn<(provider: RunProviderId) => void>(),
    selectedModel: "gpt-4o-mini",
    setSelectedModel: vi.fn(),
    providerCatalog: [],
    launchers: [],
    navigate: vi.fn<(pathname: string) => void>(),
    isPublic: false,
    setIsPublic: vi.fn(),
};

const baseRunningData = {
    title: "Workflow",
    repo: "owner/repo",
    steps: [{ title: "Step 1" }],
    currentStep: 0,
};

const baseReportData = {
    runId: null,
    result: null,
    providerLabel: null,
    model: null,
    copied: false,
    setCopied: vi.fn(),
};

function renderAppViews(overrides: Record<string, unknown> = {}) {
    const authStateOverride = (overrides.authState as Record<string, unknown>) ?? {};
    const mergedAuthState = { ...baseAuthState, ...authStateOverride };
    return render(
        <AppViews
            authState={mergedAuthState}
            authActions={{ onRequested, onAuthenticated, onLogout }}
            view={(overrides.view ?? { type: "home" }) as never}
            homeProps={baseHomeProps}
            runningData={(overrides.runningData as typeof baseRunningData) ?? baseRunningData}
            reportData={(overrides.reportData as typeof baseReportData) ?? baseReportData}
            failedRunError={overrides.failedRunError as string | null | undefined}
            onReset={vi.fn()}
            onNavigate={vi.fn()}
        />,
    );
}

afterEach(() => {
    vi.clearAllMocks();
});

describe("AppViews", () => {
    it("renders loading state when auth is not checked", () => {
        renderAppViews({ authState: { checked: false } });
        expect(screen.getByText("Loading…")).toBeInTheDocument();
    });

    it("renders check email screen on /check-email with email query", () => {
        window.history.replaceState({}, "", "/check-email?email=a%40b.com");
        renderAppViews({ authState: { checked: true } });
        expect(screen.getByText("Check your email")).toBeInTheDocument();
        expect(screen.getByText("a@b.com")).toBeInTheDocument();
    });

    it("renders check email screen on /check-email without email (generic message)", () => {
        window.history.replaceState({}, "", "/check-email");
        renderAppViews({ authState: { checked: true } });
        expect(screen.getByText("Check your email")).toBeInTheDocument();
        expect(screen.getByText(/sent to your email/)).toBeInTheDocument();
    });

    it("renders SignIn on /login for unauthenticated user", () => {
        window.history.replaceState({}, "", "/login");
        renderAppViews({ authState: { checked: true } });
        expect(screen.getByTestId("sign-in")).toBeInTheDocument();
    });

    it("renders SignIn on /signup for unauthenticated user", () => {
        window.history.replaceState({}, "", "/signup");
        renderAppViews({ authState: { checked: true } });
        expect(screen.getByTestId("sign-in")).toBeInTheDocument();
    });

    it("does not render SignIn on /login when user is already authenticated", () => {
        window.history.replaceState({}, "", "/login");
        renderAppViews({
            authState: { user: { email: "a@b.com" }, checked: true },
            view: { type: "home" },
        });
        expect(screen.queryByTestId("sign-in")).not.toBeInTheDocument();
    });

    it("calls onRequested when sign-in link is requested", async () => {
        window.history.replaceState({}, "", "/login");
        renderAppViews({ authState: { checked: true } });
        await userEvent.setup().click(screen.getByRole("button", { name: "Send link" }));
        expect(onRequested).toHaveBeenCalledWith("a@b.com");
    });

    it("calls onAuthenticated when password sign-in succeeds", async () => {
        window.history.replaceState({}, "", "/login");
        renderAppViews({ authState: { checked: true } });
        await userEvent.setup().click(screen.getByRole("button", { name: "Password sign-in" }));
        expect(onAuthenticated).toHaveBeenCalledWith(
            expect.objectContaining({ email: "pw@b.com" }),
        );
    });

    it("renders Home for unauthenticated user with home view on /", () => {
        renderAppViews({ authState: { user: null, checked: true }, view: { type: "home" } });
        expect(screen.getByTestId("home")).toBeInTheDocument();
    });

    it("renders Home for authenticated user on / (launch surface)", () => {
        window.history.replaceState({}, "", "/");
        renderAppViews({
            authState: { user: { email: "a@b.com" }, checked: true },
            view: { type: "home" },
        });
        expect(screen.getByTestId("home")).toBeInTheDocument();
        expect(screen.queryByTestId("dashboard")).not.toBeInTheDocument();
    });

    it("renders Dashboard for authenticated user on /user", () => {
        window.history.replaceState({}, "", "/user");
        renderAppViews({
            authState: { user: { email: "a@b.com" }, checked: true },
            view: { type: "home" },
        });
        expect(screen.getByTestId("dashboard")).toBeInTheDocument();
        expect(screen.getByText("a@b.com")).toBeInTheDocument();
    });

    it("renders Sign in to continue prompt on /user when unauthenticated", () => {
        window.history.replaceState({}, "", "/user");
        renderAppViews({ authState: { user: null, checked: true }, view: { type: "home" } });
        expect(screen.getByText("Sign in to continue")).toBeInTheDocument();
        expect(screen.getByRole("button", { name: "Sign in" })).toBeInTheDocument();
    });

    it("renders deep link loading state", () => {
        renderAppViews({ authState: { checked: true, deepLinkLoading: true } });
        expect(screen.getByText("Loading report…")).toBeInTheDocument();
    });

    it("renders Running for live-running view", () => {
        renderAppViews({ view: { type: "live-running", runId: "run-1", run: null } });
        expect(screen.getByTestId("running")).toBeInTheDocument();
        expect(screen.getByText(/Workflow/)).toBeInTheDocument();
    });

    it("renders Report for report view with runId", () => {
        renderAppViews({
            view: { type: "report", run: { id: "run-1", result: { summary: "OK" } } },
            reportData: {
                runId: "run-1",
                result: { summary: "OK" },
                providerLabel: "OpenAI",
                model: "gpt-4o",
                copied: false,
                setCopied: vi.fn(),
            },
        });
        expect(screen.getByTestId("report")).toBeInTheDocument();
        expect(screen.getByText("Report — run-1")).toBeInTheDocument();
    });

    it("renders failed screen with custom error message", () => {
        renderAppViews({
            view: { type: "failed", run: { id: "run-1", error: "Something broke" } },
            failedRunError: "Something broke",
        });
        expect(screen.getByText("Workflow failed")).toBeInTheDocument();
        expect(screen.getByText("Something broke")).toBeInTheDocument();
    });

    it("renders failed screen with default fallback when error is null", () => {
        renderAppViews({
            view: { type: "failed", run: { id: "run-1", error: null } },
            failedRunError: null,
        });
        expect(screen.getByText(/The run did not complete/)).toBeInTheDocument();
    });
});
