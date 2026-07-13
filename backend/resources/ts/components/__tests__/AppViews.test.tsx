import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AppViews } from "../AppViews.tsx";

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
        <div data-testid="report">Report — {runId ?? "demo"}</div>
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
    SignIn: ({ onRequested }: { onRequested: (email: string) => void }) => (
        <div data-testid="sign-in">
            <button type="button" onClick={() => onRequested("a@b.com")}>
                Send link
            </button>
        </div>
    ),
}));

const baseAuthState = {
    user: null,
    checked: false,
    checkEmail: "",
    showSignIn: false,
    deepLinkLoading: false,
};

// Use explicit generic types so vi.fn() matches AuthActions function signatures.
let setShowSignIn: ReturnType<typeof vi.fn<(v: boolean) => void>>;
let setCheckEmail: ReturnType<typeof vi.fn<(v: string) => void>>;
let onLogout: ReturnType<typeof vi.fn<() => void>>;

beforeEach(() => {
    setShowSignIn = vi.fn();
    setCheckEmail = vi.fn();
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
    launchers: [],
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
    copied: false,
    setCopied: vi.fn(),
};

function renderAppViews(overrides: Record<string, unknown> = {}) {
    const authStateOverride = (overrides.authState as Record<string, unknown>) ?? {};
    const mergedAuthState = { ...baseAuthState, ...authStateOverride };
    return render(
        <AppViews
            authState={mergedAuthState}
            authActions={{ setShowSignIn, setCheckEmail, onLogout }}
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

    it("renders check email screen when checkEmail is set", () => {
        renderAppViews({ authState: { checked: true, checkEmail: "a@b.com" } });
        expect(screen.getByText("Check your email")).toBeInTheDocument();
        expect(screen.getByText("a@b.com")).toBeInTheDocument();
    });

    it("clears checkEmail when Back is clicked", async () => {
        renderAppViews({ authState: { checked: true, checkEmail: "a@b.com" } });
        await userEvent.setup().click(screen.getByRole("button", { name: "Back" }));
        expect(setCheckEmail).toHaveBeenCalledWith("");
    });

    it("renders SignIn when showSignIn is true and user is null", () => {
        renderAppViews({ authState: { checked: true, showSignIn: true } });
        expect(screen.getByTestId("sign-in")).toBeInTheDocument();
    });

    it("calls setCheckEmail when sign-in link is requested", async () => {
        renderAppViews({ authState: { checked: true, showSignIn: true } });
        await userEvent.setup().click(screen.getByRole("button", { name: "Send link" }));
        expect(setShowSignIn).toHaveBeenCalledWith(false);
        expect(setCheckEmail).toHaveBeenCalledWith("a@b.com");
    });

    it("renders Home for unauthenticated user with home view", () => {
        renderAppViews({ authState: { user: null, checked: true }, view: { type: "home" } });
        expect(screen.getByTestId("home")).toBeInTheDocument();
    });

    it("renders Dashboard for authenticated user with home view", () => {
        renderAppViews({
            authState: { user: { email: "a@b.com" }, checked: true },
            view: { type: "home" },
        });
        expect(screen.getByTestId("dashboard")).toBeInTheDocument();
        expect(screen.getByText("a@b.com")).toBeInTheDocument();
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

    it("renders Running for demo-running view", () => {
        renderAppViews({ view: { type: "demo-running", step: 1 } });
        expect(screen.getByTestId("running")).toBeInTheDocument();
    });

    it("renders Report for report view with runId", () => {
        renderAppViews({
            view: { type: "report", run: { id: "run-1", result: { summary: "OK" } } },
            reportData: {
                runId: "run-1",
                result: { summary: "OK" },
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
