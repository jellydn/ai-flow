import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from "react";
import { launcherMetaBySlug, staticLaunchers } from "../data/launcherMeta.ts";
import { logger } from "../lib/logger.ts";
import { useRunFromPath } from "../hooks/useRunFromPath.ts";
import { useRunSubscription } from "../hooks/useRunSubscription.ts";
import {
    fetchCredentials,
    fetchProviders,
    fetchUser,
    logout as apiLogout,
    type ProviderCredential,
    type User,
} from "../services/auth.ts";
import { pickModelForProvider, type ProviderCatalogEntry } from "../lib/runModels.ts";
import {
    createRun,
    getLaunchers,
    isValidGithubUrl,
    parseGithubRepo,
    type RunProviderId,
} from "../services/run.ts";
import type { Launcher, Run } from "../types/api.ts";
import {
    initialAppUiState,
    type AppUiState,
    type ViewState,
    uiStateFromRun,
} from "./appUiState.ts";
import { isUserAccountPath } from "../lib/appPaths.ts";
import { goto } from "../lib/navigate.ts";
import { AppViews } from "./AppViews.tsx";
import { Footer } from "./Footer.tsx";
import { Header } from "./Header.tsx";
import type { HomeProps } from "./Home.tsx";

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
    const [selectedProvider, setSelectedProvider] = useState<RunProviderId>("openrouter");
    const [selectedModel, setSelectedModel] = useState("openrouter/free");
    const [providerCatalog, setProviderCatalog] = useState<ProviderCatalogEntry[]>([]);
    const [launchers, setLaunchers] = useState<Launcher[]>([]);
    const [credentials, setCredentials] = useState<ProviderCredential[]>([]);
    const [selectedCredentialId, setSelectedCredentialId] = useState<string | null>(null);
    const modelEdited = useRef(false);

    const [user, setUser] = useState<User | null>(null);
    const [authChecked, setAuthChecked] = useState(false);
    const [showSignIn, setShowSignIn] = useState(false);
    const [checkEmail, setCheckEmail] = useState("");

    useEffect(() => {
        if (!user) {
            setCredentials([]);
            setSelectedCredentialId(null);
            return;
        }
        fetchCredentials()
            .then((creds) => {
                setCredentials(creds);
                // Prefer an already-chosen key if it still exists; otherwise default/first.
                setSelectedCredentialId((current) => {
                    if (current && creds.some((c) => c.id === current)) {
                        return current;
                    }
                    if (modelEdited.current) {
                        return null;
                    }
                    const preferred = creds.find((c) => c.is_default) ?? creds[0];
                    if (
                        preferred &&
                        (preferred.provider === "openai" ||
                            preferred.provider === "openrouter" ||
                            preferred.provider === "anthropic" ||
                            preferred.provider === "gemini")
                    ) {
                        setSelectedProvider(preferred.provider);
                        setSelectedModel(preferred.default_model ?? "");
                    }
                    return preferred?.id ?? null;
                });
            })
            .catch(() => {
                setCredentials([]);
                setSelectedCredentialId(null);
            });
    }, [user]);

    useEffect(() => {
        fetchUser()
            .then(setUser)
            .catch(() => setUser(null))
            .finally(() => setAuthChecked(true));
    }, []);

    useEffect(() => {
        if (!authChecked || user) {
            return;
        }
        modelEdited.current = false;
        setSelectedProvider("openrouter");
        setSelectedModel("openrouter/free");
        if (isUserAccountPath(window.location.pathname)) {
            setShowSignIn(true);
        }
    }, [authChecked, user]);

    useEffect(() => {
        if (!user) {
            return;
        }
        modelEdited.current = false;
        setSelectedProvider("openai");
        setSelectedModel("");
    }, [user?.id]);

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
        fetchProviders()
            .then((entries) => {
                const catalog = entries.filter(
                    (e): e is ProviderCatalogEntry =>
                        e.id === "openai" ||
                        e.id === "openrouter" ||
                        e.id === "anthropic" ||
                        e.id === "gemini",
                );
                setProviderCatalog(catalog);
            })
            .catch(() => setProviderCatalog([]));
    }, []);

    useEffect(() => {
        if (!user || selectedModel !== "") {
            return;
        }
        setSelectedModel(pickModelForProvider(selectedProvider, providerCatalog, ""));
    }, [user, selectedProvider, selectedModel, providerCatalog]);

    useEffect(() => {
        getLaunchers()
            .then(setLaunchers)
            .catch((e) => {
                logger.warn("Failed to load launchers, falling back to static", e);
                setLaunchers(staticLaunchers);
                dispatch({
                    type: "patch",
                    patch: { error: e instanceof Error ? e.message : "Could not load launchers." },
                });
            });
    }, []);

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

        if (pathReady && pathRunId === null && view.type !== "home" && view.type !== "report") {
            dispatch({ type: "set-view", view: { type: "home" } });
        }
    }, [subscriptionRun, liveRunId, pathRunId, pathReady, view.type]);

    const reset = useCallback(() => {
        goto("/", navigate);
        dispatch({ type: "reset-ui" });
        setApiKey("");
        modelEdited.current = false;
        setSelectedProvider(user ? "openai" : "openrouter");
        setSelectedModel(
            user ? pickModelForProvider("openai", providerCatalog, "") : "openrouter/free",
        );
        setSelectedCredentialId(null);
        window.scrollTo({ top: 0, behavior: "smooth" });
    }, [navigate, user, providerCatalog]);

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

        setIsLaunching(true);
        try {
            const body = await createRun(
                selected,
                trimmed,
                user ? selectedProvider : "openrouter",
                user && !selectedCredentialId ? apiKey : "",
                user ? (selectedCredentialId ?? undefined) : undefined,
                user ? selectedModel || undefined : "openrouter/free",
            );
            goto(`/runs/${body.id}`, navigate);
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
    }, [
        url,
        selected,
        selectedProvider,
        selectedModel,
        apiKey,
        selectedCredentialId,
        navigate,
        user,
    ]);

    const liveProgress = view.type === "live-running" ? (view.run?.progress ?? []) : [];
    const liveSteps =
        liveProgress.length > 0
            ? liveProgress.map((message) => ({ title: message }))
            : [{ title: "Waiting for the workflow to start", detail: "In queue" }];
    const liveCurrentStep = liveProgress.length > 0 ? liveProgress.length - 1 : 0;

    const runningTitle = activeMeta?.title ?? activeLauncher?.name ?? "Workflow";
    const runningRepo = parsedRepo || "…";

    const homeProps: HomeProps = {
        selected,
        setSelected,
        url,
        setUrl,
        error,
        setError,
        launch,
        isLaunching,
        apiKey,
        setApiKey,
        selectedProvider,
        setSelectedProvider: (provider) => {
            modelEdited.current = true;
            setSelectedProvider(provider);
            setSelectedModel(pickModelForProvider(provider, providerCatalog, ""));
        },
        selectedModel,
        setSelectedModel: (model) => {
            modelEdited.current = true;
            setSelectedModel(model);
        },
        providerCatalog,
        launchers,
        credentials,
        selectedCredentialId,
        setSelectedCredentialId,
        navigate,
        user,
        onManageApiKeys: user
            ? () => {
                  goto("/user", navigate);
              }
            : undefined,
    };

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
                        goto("/user", navigate);
                    } else {
                        setShowSignIn(!showSignIn);
                    }
                }}
                onLaunchClick={() => {
                    goto("/", navigate);
                    window.scrollTo({ top: 0, behavior: "smooth" });
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            document
                                .querySelector("#launcher")
                                ?.scrollIntoView({ behavior: "smooth" });
                        });
                    });
                }}
            />

            <AppViews
                authState={{
                    user,
                    checked: authChecked,
                    checkEmail,
                    showSignIn,
                    deepLinkLoading,
                }}
                authActions={{
                    setShowSignIn,
                    setCheckEmail,
                    onAuthenticated: (signedIn) => {
                        setUser(signedIn);
                        setShowSignIn(false);
                        goto("/user", navigate);
                    },
                    onLogout: async () => {
                        await apiLogout();
                        setUser(null);
                        goto("/", navigate);
                    },
                }}
                view={view}
                homeProps={homeProps}
                runningData={{
                    title: runningTitle,
                    repo: runningRepo,
                    steps: liveSteps,
                    currentStep: liveCurrentStep,
                }}
                reportData={{
                    runId: view.type === "report" ? (view.run?.id ?? null) : null,
                    result: view.type === "report" ? (view.run?.result ?? null) : null,
                    providerLabel:
                        view.type === "report"
                            ? (view.run?.provider_label ?? view.run?.provider ?? null)
                            : null,
                    model: view.type === "report" ? (view.run?.model ?? null) : null,
                    copied,
                    setCopied,
                }}
                failedRunError={view.type === "failed" ? view.run.error : undefined}
                onReset={reset}
                onNavigate={navigate}
            />

            <Footer />
        </div>
    );
}
