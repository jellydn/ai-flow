import {
    getEmailFromQuery,
    isCheckEmailPath,
    isSignInPath,
    isUserAccountPath,
} from "../lib/appPaths.ts";
import { goto } from "../lib/navigate.ts";
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
    deepLinkLoading: boolean;
}

interface AuthActions {
    onRequested: (email: string) => void;
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
    providerLabel: string | null;
    model: string | null;
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
    const { user, checked, deepLinkLoading } = authState;
    const { onRequested, onAuthenticated, onLogout } = authActions;

    const pathname = window.location.pathname;
    const onUserRoute = isUserAccountPath(pathname);
    const onCheckEmail = isCheckEmailPath(pathname);
    const onSignInRoute = isSignInPath(pathname);
    // An authenticated user on /login, /signup, or /check-email is redirected to
    // /user by an effect in App.tsx — but render Home meanwhile to avoid a flash.
    const showSignIn = onSignInRoute && !user && checked;
    const checkEmail = onCheckEmail ? getEmailFromQuery() : "";
    const showDashboard = Boolean(user && checked && onUserRoute && !deepLinkLoading);
    const showHome =
        view.type === "home" &&
        !deepLinkLoading &&
        !showSignIn &&
        !onCheckEmail &&
        checked &&
        !onUserRoute;

    return (
        <>
            {onCheckEmail && !user && (
                <main className="auth-page">
                    <div className="auth-card">
                        <p className="auth-kicker">Account</p>
                        <h2>Check your email</h2>
                        <p className="auth-check-message" role="status" aria-live="polite">
                            {checkEmail ? (
                                <>
                                    A sign-in link was sent to <strong>{checkEmail}</strong>. Open
                                    the email and click the link to continue.
                                </>
                            ) : (
                                <>
                                    A sign-in link was sent to your email. Open the email and click
                                    the link to continue.
                                </>
                            )}
                        </p>
                        <button
                            type="button"
                            className="auth-card-back"
                            onClick={() => goto("/login", onNavigate)}
                        >
                            Back to sign in
                        </button>
                    </div>
                </main>
            )}

            {!checked && !onCheckEmail && (
                <main className="running-page">
                    <div className="error-fallback">
                        <p>Loading…</p>
                    </div>
                </main>
            )}

            {showSignIn && <SignIn onRequested={onRequested} onAuthenticated={onAuthenticated} />}

            {showDashboard && <Dashboard user={user!} navigate={onNavigate} onLogout={onLogout} />}

            {onUserRoute && checked && !user && !showSignIn && !onCheckEmail && (
                <main className="auth-page">
                    <div className="auth-card">
                        <p className="auth-kicker">Account</p>
                        <h2>Sign in to continue</h2>
                        <p>Open your account dashboard, run history, and saved API keys.</p>
                        <button
                            type="button"
                            className="auth-card-back"
                            onClick={() => goto("/login", onNavigate)}
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

            {view.type === "live-running" && (
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
                    providerLabel={reportData.providerLabel}
                    model={reportData.model}
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
