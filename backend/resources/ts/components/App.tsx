import { useEffect, useMemo, useState } from 'react';
import {
    createExecution,
    getExecution,
    getFlows,
    isValidGithubUrl,
    parseGithubRepo,
    subscribeToExecution,
} from '../services/api.ts';
import type { Execution, ExecutionStatus } from '../types/api.ts';
import { buildWorkflows, demoExecutionSteps, workflowBySlug, workflows } from '../data/workflows.ts';
import { Footer } from './Footer.tsx';
import { Header } from './Header.tsx';
import { Home } from './Home.tsx';
import { Report } from './Report.tsx';
import { Running } from './Running.tsx';

const DEMO_MODE = import.meta.env.VITE_DEMO_MODE === 'true';
const DEMO_COMPLETE_DELAY_MS = 650;
const DEMO_STEP_DELAY_MS = 780;

function viewFromStatus(status: ExecutionStatus): 'running' | 'report' | 'failed' {
    if (status === 'completed') return 'report';
    if (status === 'failed') return 'failed';
    return 'running';
}

export function App() {
    const [selected, setSelected] = useState('review');
    const [url, setUrl] = useState('');
    const [view, setView] = useState<'home' | 'running' | 'report' | 'failed'>('home');
    const [step, setStep] = useState(0);
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState('');
    const [mobileOpen, setMobileOpen] = useState(false);
    const [runId, setRunId] = useState<string | null>(null);
    const [runSnapshot, setRunSnapshot] = useState<Execution | null>(null);
    const [isLaunching, setIsLaunching] = useState(false);
    const [apiKey, setApiKey] = useState('');
    const [availableSlugs, setAvailableSlugs] = useState<Set<string> | null>(null);
    const [liveWorkflows, setLiveWorkflows] = useState(workflows);

    const activeWorkflow = useMemo(() => liveWorkflows.find((item) => item.id === selected), [liveWorkflows, selected]);
    const parsedRepo = useMemo(() => parseGithubRepo(url) ?? '', [url]);

    useEffect(() => {
        getFlows()
            .then((flows) => {
                setAvailableSlugs(new Set(flows.map((flow) => flow.slug)));
                const built = buildWorkflows(flows);
                if (built.length > 0) {
                    setLiveWorkflows(built);
                }
            })
            .catch(() => setAvailableSlugs(new Set()));
    }, []);

    useEffect(() => {
        if (DEMO_MODE) return undefined;

        const loadFromPath = async (pathname: string) => {
            const match = pathname.match(/^\/runs\/([0-9a-f-]+)\/?$/i);
            if (!match) return;

            const id = match[1];
            try {
                const snapshot = await getExecution(id);
                const workflow = workflowBySlug[snapshot.flowId ?? ''];
                setSelected(workflow?.id ?? 'review');
                setUrl(snapshot.input?.source_url ?? '');
                setRunId(snapshot.id);
                setRunSnapshot(snapshot);
                setView(viewFromStatus(snapshot.status));
            } catch (e) {
                setError(e instanceof Error ? e.message : 'Could not load this report.');
                setView('home');
            }
        };

        loadFromPath(window.location.pathname);

        const handlePopState = () => {
            const pathname = window.location.pathname;
            if (pathname === '/') {
                resetWithoutUrl();
            } else {
                loadFromPath(pathname);
            }
        };

        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    useEffect(() => {
        if (view !== 'running' || runId) return undefined;
        if (step >= demoExecutionSteps.length) {
            const done = setTimeout(() => setView('report'), DEMO_COMPLETE_DELAY_MS);
            return () => clearTimeout(done);
        }
        const timer = setTimeout(() => setStep((value) => value + 1), DEMO_STEP_DELAY_MS);
        return () => clearTimeout(timer);
    }, [view, step, runId]);

    useEffect(() => {
        if (!runId || view !== 'running') return undefined;

        const unsubscribe = subscribeToExecution(runId, {
            onSnapshot: (snapshot) => setRunSnapshot(snapshot),
            onTerminal: (snapshot, type) => {
                setRunSnapshot(snapshot);
                setView(type === 'completed' ? 'report' : 'failed');
            },
        });

        return () => unsubscribe();
    }, [runId, view]);

    const reset = () => {
        window.history.pushState({}, '', '/');
        setView('home');
        setStep(0);
        setUrl('');
        setRunId(null);
        setRunSnapshot(null);
        setApiKey('');
        setError('');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const resetWithoutUrl = () => {
        setView('home');
        setStep(0);
        setRunId(null);
        setRunSnapshot(null);
        setApiKey('');
        setError('');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const launch = async () => {
        const trimmed = url.trim();
        if (!trimmed || !isValidGithubUrl(trimmed)) {
            setError('Enter a valid public GitHub repository, issue, or pull request URL.');
            return;
        }
        if (!activeWorkflow?.slug) {
            setError('This workflow is not available on the API yet. Pick Review, Plan, Explain, or Laravel doctor.');
            return;
        }
        if (availableSlugs !== null && !availableSlugs.has(activeWorkflow.slug)) {
            setError('This workflow is not currently active. Pick Review, Plan, Explain, or Laravel doctor.');
            return;
        }

        setError('');
        setStep(0);
        setRunId(null);
        setRunSnapshot(null);

        if (DEMO_MODE) {
            setView('running');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        setIsLaunching(true);
        try {
            const body = await createExecution(activeWorkflow.slug, trimmed, apiKey);
            setRunId(body.id);
            window.history.pushState({}, '', `/runs/${body.id}`);
            setView('running');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not start workflow. Is the API running?');
        } finally {
            setApiKey('');
            setIsLaunching(false);
        }
    };

    const progressMessages = runSnapshot?.progress ?? [];
    const progressLines = runId
        ? (progressMessages.length > 0
            ? progressMessages.map((line) => [line, ''] as [string, string])
            : [['Waiting for the workflow to start', 'In queue'] as [string, string]])
        : demoExecutionSteps;

    const progressIndex = runId ? Math.max(0, progressMessages.length - 1) : step;

    return (
        <div className="app-shell">
            <Header mobileOpen={mobileOpen} setMobileOpen={setMobileOpen} reset={reset} />

            {view === 'home' && (
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
                    workflows={liveWorkflows}
                />
            )}
            {view === 'running' && (
                <Running
                    activeWorkflow={activeWorkflow}
                    repo={parsedRepo || '…'}
                    step={progressIndex}
                    executionSteps={progressLines}
                    live={Boolean(runId)}
                />
            )}
            {view === 'report' && (
                <Report
                    workflow={activeWorkflow}
                    repo={parsedRepo}
                    copied={copied}
                    setCopied={setCopied}
                    reset={reset}
                    runId={runId}
                    result={runSnapshot?.result ?? null}
                />
            )}
            {view === 'failed' && (
                <main className="running-page">
                    <div className="error-fallback">
                        <h1>Workflow failed</h1>
                        <p>{runSnapshot?.error || 'The run did not complete. Try again or check the API logs.'}</p>
                        <button type="button" onClick={reset}>← New launch</button>
                    </div>
                </main>
            )}

            <Footer />
        </div>
    );
}
