import { isUserAccountPath } from "../lib/appPaths.ts";
import type { User } from "../services/auth.ts";
import type { RunResult } from "../types/api.ts";
import type { ViewState } from "./appUiState.ts";
import { Dashboard } from "./Dashboard.tsx";
import { Home, type HomeProps } from "./Home.tsx";
import { Report } from "./Report.tsx";
import { Running } from "./Running.tsx";
import { SignIn } from "./SignIn.tsx";

interface AuthState {
    user: User | null;
    checked: boolean;
    checkEmail: string;
    showSignIn: boolean;
    deepLinkLoading: boolean;
}

interface AuthActions {
    setShowSignIn: (v: boolean) => void;
    setCheckEmail: (v: string) => void;
    onAuthenticated: (user: User) => void;
    onLogout: () => void;
}

interface RunningData {
    title: string;
    repo: string;
    steps: { title: string }[];
    currentStep: number;
}

interface ReportData {
    runId: string | null;
    result: RunResult | null;
    copied: boolean;
    setCopied: (v: boolean) => void;
}

interface AppViewsProps {
    authState: AuthState;
    authActions: AuthActions;
    view: ViewState;
    homeProps: HomeProps;
    runningData: RunningData;
    reportData: ReportData;
    failedRunError: string | null | undefined;
    onReset: () => void;
    onNavigate: (path: string) => void;
}

export function AppViews({
    authState,
    authActions,
    view,
    homeProps,
    runningData,
    reportData,
    failedRunError,
    onReset,
    onNavigate,
}: AppViewsProps) {
    const { user, checked, checkEmail, showSignIn, deepLinkLoading } = authState;
    const { setShowSignIn, setCheckEmail, onAuthenticated, onLogout } = authActions;

    const onUserRoute = isUserAccountPath(window.location.pathname);
    const showDashboard = Boolean(user && checked && onUserRoute && !deepLinkLoading);
    const showHome =
        view.type === "home" &&
        !deepLinkLoading &&
        !showSignIn &&
        !checkEmail &&
        checked &&
        !onUserRoute;

    return (
        <>
            {checkEmail && (
                <main className="auth-page">
                    <div className="auth-card">
                        <p className="auth-kicker">Account</p>
                        <h2>Check your email</h2>
                        <p className="auth-check-message" role="status" aria-live="polite">
                            A sign-in link was sent to <strong>{checkEmail}</strong>. Open the email
                            and click the link to continue.
                        </p>
                        <button
                            type="button"
                            className="auth-card-back"
                            onClick={() => setCheckEmail("")}
                        >
                            Back to sign in
                        </button>
                    </div>
                </main>
            )}

            {!checked && !checkEmail && (
                <main className="running-page">
                    <div className="error-fallback">
                        <p>Loading…</p>
                    </div>
                </main>
            )}

            {showSignIn && !user && !checkEmail && (
                <SignIn
                    onRequested={(email) => {
                        setShowSignIn(false);
                        setCheckEmail(email);
                    }}
                    onAuthenticated={(signedIn) => {
                        setShowSignIn(false);
                        onAuthenticated(signedIn);
                    }}
                />
            )}

            {showDashboard && <Dashboard user={user!} navigate={onNavigate} onLogout={onLogout} />}

            {onUserRoute && checked && !user && !showSignIn && !checkEmail && (
                <main className="auth-page">
                    <div className="auth-card">
                        <p className="auth-kicker">Account</p>
                        <h2>Sign in to continue</h2>
                        <p>Open your account dashboard, run history, and saved API keys.</p>
                        <button
                            type="button"
                            className="auth-card-back"
                            onClick={() => setShowSignIn(true)}
                        >
                            Sign in
                        </button>
                    </div>
                </main>
            )}

            {deepLinkLoading && (
                <main className="running-page">
                    <div className="error-fallback">
                        <h1>Loading report…</h1>
                        <p>Fetching workflow status for this link.</p>
                    </div>
                </main>
            )}

            {showHome && <Home {...homeProps} />}

            {(view.type === "demo-running" || view.type === "live-running") && (
                <Running
                    title={runningData.title}
                    repo={runningData.repo}
                    steps={runningData.steps}
                    currentStep={runningData.currentStep}
                />
            )}

            {view.type === "report" && (
                <Report
                    launcherName={runningData.title}
                    repo={runningData.repo}
                    copied={reportData.copied}
                    setCopied={reportData.setCopied}
                    reset={onReset}
                    runId={reportData.runId}
                    result={reportData.result}
                />
            )}

            {view.type === "failed" && (
                <main className="running-page">
                    <div className="error-fallback">
                        <h1>Workflow failed</h1>
                        <p>
                            {failedRunError ||
                                "The run did not complete. Try again or check the API logs."}
                        </p>
                        <button type="button" onClick={onReset}>
                            ← New launch
                        </button>
                    </div>
                </main>
            )}
        </>
    );
}
