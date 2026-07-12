import { useCallback, useEffect, useMemo, useState } from 'react';
import { demoSteps, launcherMetaBySlug, staticLaunchers } from '../data/launcherMeta.ts';
import { useRunFromPath } from '../hooks/useRunFromPath.ts';
import { useRunSubscription } from '../hooks/useRunSubscription.ts';
import { createRun, getLaunchers, isValidGithubUrl, parseGithubRepo } from '../services/run.ts';
import type { Launcher, Run } from '../types/api.ts';
import { Footer } from './Footer.tsx';
import { Header } from './Header.tsx';
import { Home } from './Home.tsx';
import { Report } from './Report.tsx';
import { Running } from './Running.tsx';

const DEMO_MODE = import.meta.env.VITE_DEMO_MODE === 'true';
const DEMO_COMPLETE_DELAY_MS = 650;
const DEMO_STEP_DELAY_MS = 780;

type ViewState =
    | { type: 'home' }
    | { type: 'demo-running'; step: number }
    | { type: 'live-running'; runId: string; run: Run | null }
    | { type: 'report'; run: Run | null }
    | { type: 'failed'; run: Run };

export function App() {
    const [selected, setSelected] = useState('review-pr');
    const [url, setUrl] = useState('');
    const [view, setView] = useState<ViewState>({ type: 'home' });
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState('');
    const [mobileOpen, setMobileOpen] = useState(false);
    const [isLaunching, setIsLaunching] = useState(false);
    const [apiKey, setApiKey] = useState('');
    const [launchers, setLaunchers] = useState<Launcher[]>([]);

    const { runId: pathRunId, ready: pathReady, navigate } = useRunFromPath();

    const activeLauncher = useMemo(() => launchers.find((launcher) => launcher.slug === selected), [launchers, selected]);
    const activeMeta = useMemo(() => launcherMetaBySlug[selected], [selected]);
    const parsedRepo = useMemo(() => parseGithubRepo(url) ?? '', [url]);

    const liveRunId = view.type === 'live-running' ? view.runId : (pathRunId ?? null);
    const liveInitialRun = view.type === 'live-running' ? view.run : null;
    const { run: subscriptionRun } = useRunSubscription(liveRunId, liveInitialRun);

    useEffect(() => {
        if (DEMO_MODE) {
            setLaunchers(staticLaunchers);
            return;
        }

        getLaunchers()
            .then(setLaunchers)
            .catch((e) => {
                setLaunchers(staticLaunchers);
                setError(e instanceof Error ? e.message : 'Could not load launchers.');
            });
    }, []);

    useEffect(() => {
        if (view.type !== 'demo-running') return;
        if (view.step >= demoSteps.length) {
            const done = setTimeout(() => setView({ type: 'report', run: null }), DEMO_COMPLETE_DELAY_MS);
            return () => clearTimeout(done);
        }
        const timer = setTimeout(() => setView((current) => {
            if (current.type !== 'demo-running') return current;
            return { ...current, step: current.step + 1 };
        }), DEMO_STEP_DELAY_MS);
        return () => clearTimeout(timer);
    }, [view.type, view.type === 'demo-running' ? view.step : 0]);

    useEffect(() => {
        const run = subscriptionRun?.id === liveRunId ? subscriptionRun : null;

        if (run && view.type !== 'report') {
            setSelected(run.launcher ? (launcherMetaBySlug[run.launcher]?.slug ?? run.launcher) : 'review-pr');
            setUrl(run.input?.source_url ?? '');
            if (run.status === 'completed') {
                setView({ type: 'report', run });
            } else if (run.status === 'failed') {
                setView({ type: 'failed', run });
            } else {
                setView({ type: 'live-running', runId: run.id, run });
            }
            return;
        }

        if (pathReady && pathRunId === null && view.type !== 'home' && view.type !== 'demo-running') {
            setView({ type: 'home' });
        }
    }, [subscriptionRun, liveRunId, pathRunId, pathReady, view.type]);

    const reset = useCallback(() => {
        window.history.pushState({}, '', '/');
        navigate('/');
        setView({ type: 'home' });
        setUrl('');
        setApiKey('');
        setError('');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [navigate]);

    const launch = useCallback(async () => {
        const trimmed = url.trim();
        if (!trimmed || !isValidGithubUrl(trimmed)) {
            setError('Enter a valid public GitHub repository, issue, or pull request URL.');
            return;
        }

        setError('');

        if (DEMO_MODE) {
            setView({ type: 'demo-running', step: 0 });
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        setIsLaunching(true);
        try {
            const body = await createRun(selected, trimmed, apiKey);
            window.history.pushState({}, '', `/runs/${body.id}`);
            navigate(`/runs/${body.id}`);
            setView({ type: 'live-running', runId: body.id, run: null });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (e) {
            setView({ type: 'home' });
            setError(e instanceof Error ? e.message : 'Could not start workflow. Is the API running?');
        } finally {
            setApiKey('');
            setIsLaunching(false);
        }
    }, [url, selected, apiKey, navigate]);

    const liveProgress = view.type === 'live-running' ? (view.run?.progress ?? []) : [];
    const liveSteps = liveProgress.length > 0
        ? liveProgress.map((message) => ({ title: message }))
        : [{ title: 'Waiting for the workflow to start', detail: 'In queue' }];
    const liveCurrentStep = liveProgress.length > 0 ? liveProgress.length - 1 : 0;

    const runningTitle = activeMeta?.title ?? activeLauncher?.name ?? 'Workflow';
    const runningRepo = parsedRepo || '…';

    return (
        <div className="app-shell">
            <Header mobileOpen={mobileOpen} setMobileOpen={setMobileOpen} reset={reset} />

            {view.type === 'home' && (
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

            {(view.type === 'demo-running' || view.type === 'live-running') && (
                <Running
                    title={runningTitle}
                    repo={runningRepo}
                    steps={view.type === 'demo-running' ? demoSteps : liveSteps}
                    currentStep={view.type === 'demo-running' ? view.step : liveCurrentStep}
                />
            )}

            {view.type === 'report' && (
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

            {view.type === 'failed' && (
                <main className="running-page">
                    <div className="error-fallback">
                        <h1>Workflow failed</h1>
                        <p>{view.run.error || 'The run did not complete. Try again or check the API logs.'}</p>
                        <button type="button" onClick={reset}>← New launch</button>
                    </div>
                </main>
            )}

            <Footer />
        </div>
    );
}
