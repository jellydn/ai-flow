import { useCallback, useEffect, useMemo, useReducer, useState } from "react";
import { demoSteps, launcherMetaBySlug, staticLaunchers } from "../data/launcherMeta.ts";
import { useRunFromPath } from "../hooks/useRunFromPath.ts";
import { useRunSubscription } from "../hooks/useRunSubscription.ts";
import { fetchUser, logout as apiLogout, type User } from "../services/auth.ts";
import { createRun, getLaunchers, isValidGithubUrl, parseGithubRepo } from "../services/run.ts";
import type { Launcher, Run } from "../types/api.ts";
import {
    initialAppUiState,
    type AppUiState,
    type ViewState,
    uiStateFromRun,
} from "./appUiState.ts";
import { Dashboard } from "./Dashboard.tsx";
import { Footer } from "./Footer.tsx";
import { Header } from "./Header.tsx";
import { Home } from "./Home.tsx";
import { Report } from "./Report.tsx";
import { Running } from "./Running.tsx";
import { SignIn } from "./SignIn.tsx";

const DEMO_MODE = import.meta.env.VITE_DEMO_MODE === "true";
const DEMO_COMPLETE_DELAY_MS = 650;
const DEMO_STEP_DELAY_MS = 780;

type UiAction =
    | { type: "patch"; patch: Partial<AppUiState> }
    | { type: "sync-run"; run: Run }
    | { type: "path-error"; message: string }
    | { type: "set-view"; view: ViewState }
    | { type: "reset-ui" };

function uiReducer(state: AppUiState, action: UiAction): AppUiState {
    switch (action.type) {
        case "patch":
            return { ...state, ...action.patch };
        case "sync-run":
            return uiStateFromRun(state, action.run);
        case "path-error":
            return { ...state, view: { type: "home" }, error: action.message };
        case "set-view":
            return { ...state, view: action.view };
        case "reset-ui":
            return { ...initialAppUiState };
        default:
            return state;
    }
}

export function App() {
    const [ui, dispatch] = useReducer(uiReducer, initialAppUiState);
    const { selected, url, view, error } = ui;

    const [copied, setCopied] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [isLaunching, setIsLaunching] = useState(false);
    const [apiKey, setApiKey] = useState("");
    const [launchers, setLaunchers] = useState<Launcher[]>([]);

    const [user, setUser] = useState<User | null>(null);
    const [authChecked, setAuthChecked] = useState(false);
    const [showSignIn, setShowSignIn] = useState(false);
    const [checkEmail, setCheckEmail] = useState("");

    useEffect(() => {
        fetchUser()
            .then(setUser)
            .catch(() => setUser(null))
            .finally(() => setAuthChecked(true));
    }, []);

    const {
        runId: pathRunId,
        run: pathRun,
        loading: pathLoading,
        error: pathError,
        ready: pathReady,
        navigate,
    } = useRunFromPath();

    const activeLauncher = useMemo(
        () => launchers.find((launcher) => launcher.slug === selected),
        [launchers, selected],
    );
    const activeMeta = useMemo(() => launcherMetaBySlug[selected], [selected]);
    const parsedRepo = useMemo(() => parseGithubRepo(url) ?? "", [url]);

    // Keep the subscription alive across view transitions (live-running → report / failed)
    // so the EventSource is not torn down and recreated on every state cycle.
    // The subscription hook handles its own terminal cleanup.
    const liveRunId = view.type === "live-running" ? view.runId : (pathRunId ?? null);
    let liveInitialRun: Run | null = null;
    if (liveRunId && pathRun?.id === liveRunId) {
        liveInitialRun = pathRun;
    } else if (view.type === "live-running") {
        liveInitialRun = view.run;
    }
    const deepLinkLoading = Boolean(pathRunId && pathLoading);
    const { run: subscriptionRun } = useRunSubscription(liveRunId, liveInitialRun);

    const setSelected = useCallback((value: string) => {
        dispatch({ type: "patch", patch: { selected: value } });
    }, []);
    const setUrl = useCallback((value: string) => {
        dispatch({ type: "patch", patch: { url: value } });
    }, []);
    const setError = useCallback((value: string) => {
        dispatch({ type: "patch", patch: { error: value } });
    }, []);

    useEffect(() => {
        if (DEMO_MODE) {
            setLaunchers(staticLaunchers);
            return;
        }

        getLaunchers()
            .then(setLaunchers)
            .catch((e) => {
                setLaunchers(staticLaunchers);
                dispatch({
                    type: "patch",
                    patch: { error: e instanceof Error ? e.message : "Could not load launchers." },
                });
            });
    }, []);

    const demoRunningStep = view.type === "demo-running" ? view.step : undefined;

    useEffect(() => {
        if (demoRunningStep === undefined) {
            return;
        }
        if (demoRunningStep >= demoSteps.length) {
            const done = setTimeout(
                () => dispatch({ type: "set-view", view: { type: "report", run: null } }),
                DEMO_COMPLETE_DELAY_MS,
            );
            return () => clearTimeout(done);
        }
        const timer = setTimeout(() => {
            dispatch({
                type: "set-view",
                view: { type: "demo-running", step: demoRunningStep + 1 },
            });
        }, DEMO_STEP_DELAY_MS);
        return () => clearTimeout(timer);
    }, [demoRunningStep]);

    useEffect(() => {
        if (!pathReady || !pathRunId) {
            return;
        }
        if (pathError) {
            dispatch({ type: "path-error", message: pathError });
            return;
        }
        if (pathLoading || !pathRun) {
            return;
        }
        if (subscriptionRun?.id === pathRunId) {
            return;
        }
        dispatch({ type: "sync-run", run: pathRun });
    }, [pathReady, pathRunId, pathRun, pathLoading, pathError, subscriptionRun?.id]);

    useEffect(() => {
        const run = subscriptionRun?.id === liveRunId ? subscriptionRun : null;

        if (run && view.type !== "report") {
            dispatch({ type: "sync-run", run });
            return;
        }

        if (
            pathReady &&
            pathRunId === null &&
            view.type !== "home" &&
            view.type !== "demo-running"
        ) {
            dispatch({ type: "set-view", view: { type: "home" } });
        }
    }, [subscriptionRun, liveRunId, pathRunId, pathReady, view.type]);

    const reset = useCallback(() => {
        window.history.pushState({}, "", "/");
        navigate("/");
        dispatch({ type: "reset-ui" });
        setApiKey("");
        window.scrollTo({ top: 0, behavior: "smooth" });
    }, [navigate]);

    const launch = useCallback(async () => {
        const trimmed = url.trim();
        if (!trimmed || !isValidGithubUrl(trimmed)) {
            dispatch({
                type: "patch",
                patch: {
                    error: "Enter a valid public GitHub repository, issue, or pull request URL.",
                },
            });
            return;
        }

        dispatch({ type: "patch", patch: { error: "" } });

        if (DEMO_MODE) {
            dispatch({ type: "set-view", view: { type: "demo-running", step: 0 } });
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }

        setIsLaunching(true);
        try {
            const body = await createRun(selected, trimmed, apiKey);
            window.history.pushState({}, "", `/runs/${body.id}`);
            navigate(`/runs/${body.id}`);
            dispatch({
                type: "set-view",
                view: { type: "live-running", runId: body.id, run: null },
            });
            window.scrollTo({ top: 0, behavior: "smooth" });
        } catch (e) {
            dispatch({
                type: "patch",
                patch: {
                    view: { type: "home" },
                    error:
                        e instanceof Error
                            ? e.message
                            : "Could not start workflow. Is the API running?",
                },
            });
        } finally {
            setApiKey("");
            setIsLaunching(false);
        }
    }, [url, selected, apiKey, navigate]);

    const liveProgress = view.type === "live-running" ? (view.run?.progress ?? []) : [];
    const liveSteps =
        liveProgress.length > 0
            ? liveProgress.map((message) => ({ title: message }))
            : [{ title: "Waiting for the workflow to start", detail: "In queue" }];
    const liveCurrentStep = liveProgress.length > 0 ? liveProgress.length - 1 : 0;

    const runningTitle = activeMeta?.title ?? activeLauncher?.name ?? "Workflow";
    const runningRepo = parsedRepo || "…";

    return (
        <div className="app-shell">
            <Header
                mobileOpen={mobileOpen}
                setMobileOpen={setMobileOpen}
                reset={reset}
                user={user}
                onAuthClick={() => {
                    if (user) {
                        setShowSignIn(false);
                    } else {
                        setShowSignIn(!showSignIn);
                    }
                }}
            />

            {checkEmail && (
                <div className="auth-page">
                    <div className="auth-card">
                        <h2>Check your email</h2>
                        <p>
                            A sign-in link was sent to <strong>{checkEmail}</strong>. Click the link
                            in the email to continue.
                        </p>
                        <button type="button" onClick={() => setCheckEmail("")}>
                            Back
                        </button>
                    </div>
                </div>
            )}

            {!authChecked && !checkEmail && (
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
                />
            )}

            {user && !deepLinkLoading && view.type === "home" && authChecked && (
                <Dashboard
                    user={user}
                    onLogout={async () => {
                        await apiLogout();
                        setUser(null);
                    }}
                />
            )}

            {deepLinkLoading && (
                <main className="running-page">
                    <div className="error-fallback">
                        <h1>Loading report…</h1>
                        <p>Fetching workflow status for this link.</p>
                    </div>
                </main>
            )}

            {!user &&
                view.type === "home" &&
                !deepLinkLoading &&
                !showSignIn &&
                !checkEmail &&
                authChecked && (
                    <Home
                        selected={selected}
                        setSelected={setSelected}
                        url={url}
                        setUrl={setUrl}
                        error={error}
                        setError={setError}
                        launch={launch}
                        isLaunching={isLaunching}
                        apiKey={apiKey}
                        setApiKey={setApiKey}
                        launchers={launchers}
                    />
                )}

            {(view.type === "demo-running" || view.type === "live-running") && (
                <Running
                    title={runningTitle}
                    repo={runningRepo}
                    steps={view.type === "demo-running" ? demoSteps : liveSteps}
                    currentStep={view.type === "demo-running" ? view.step : liveCurrentStep}
                />
            )}

            {view.type === "report" && (
                <Report
                    launcherName={runningTitle}
                    repo={parsedRepo}
                    copied={copied}
                    setCopied={setCopied}
                    reset={reset}
                    runId={view.run?.id ?? null}
                    result={view.run?.result ?? null}
                />
            )}

            {view.type === "failed" && (
                <main className="running-page">
                    <div className="error-fallback">
                        <h1>Workflow failed</h1>
                        <p>
                            {view.run.error ||
                                "The run did not complete. Try again or check the API logs."}
                        </p>
                        <button type="button" onClick={reset}>
                            ← New launch
                        </button>
                    </div>
                </main>
            )}

            <Footer />
        </div>
    );
}
