import type { CreateExecutionResponse, Execution, ExecutionResult, ExecutionStatus, Finding, Flow } from '../types/api.ts';

const DEFAULT_TIMEOUT_MS = 10_000;

async function withTimeout<T>(promise: Promise<T>, ms: number): Promise<T> {
    return Promise.race([
        promise,
        new Promise<never>((_, reject) => setTimeout(() => reject(new Error('Request timed out')), ms)),
    ]);
}

async function parseJson(response: Response): Promise<unknown> {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch {
        return {};
    }
}

function buildErrorMessage(response: Response, body: Record<string, unknown>): string {
    if (body.message && typeof body.message === 'string') {
        return body.message;
    }

    if (body.errors && typeof body.errors === 'object' && body.errors !== null) {
        const messages = Object.values(body.errors)
            .flat()
            .filter((item): item is string => typeof item === 'string');
        if (messages.length > 0) {
            return messages.join(' ');
        }
    }

    return response.statusText || `Request failed (${response.status})`;
}

function toFlow(value: unknown): Flow {
    const item = value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
    return {
        id: String(item.id ?? item.slug ?? ''),
        slug: String(item.slug ?? ''),
        name: String(item.name ?? ''),
        description: String(item.description ?? ''),
        inputType: String(item.input_type ?? item.inputType ?? ''),
    };
}

function toFindings(value: unknown): Finding[] | undefined {
    if (!Array.isArray(value)) {
        return undefined;
    }

    return value.map((finding) => {
        const f = finding && typeof finding === 'object' ? (finding as Record<string, unknown>) : {};
        return {
            severity: String(f.severity ?? ''),
            title: String(f.title ?? ''),
            description: String(f.description ?? ''),
            recommendation: String(f.recommendation ?? ''),
        };
    });
}

function toExecutionResult(value: unknown): ExecutionResult | null {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value !== 'object') {
        return null;
    }

    const result = value as Record<string, unknown>;
    return {
        summary: String(result.summary ?? ''),
        risk: result.risk ? String(result.risk) : undefined,
        findings: toFindings(result.findings),
        verificationSteps: Array.isArray(result.verification_steps)
            ? result.verification_steps.map((item) => String(item))
            : undefined,
    };
}

function toExecutionStatus(value: unknown): ExecutionStatus {
    if (value === 'queued' || value === 'running' || value === 'completed' || value === 'failed') {
        return value;
    }
    return 'running';
}

export function toExecution(value: unknown): Execution {
    const data = value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
    return {
        id: String(data.id ?? ''),
        flowId: data.launcher ? String(data.launcher) : (data.flowId ? String(data.flowId) : null),
        input: data.input && typeof data.input === 'object' ? (data.input as { source_url?: string }) : null,
        status: toExecutionStatus(data.status),
        progress: Array.isArray(data.progress) ? data.progress.map((item) => String(item)) : [],
        result: toExecutionResult(data.result),
        error: data.error === null || data.error === undefined ? null : String(data.error),
        startedAt: data.started_at ? String(data.started_at) : null,
        completedAt: data.completed_at ? String(data.completed_at) : null,
    };
}

export async function getHealth(): Promise<{ status: string }> {
    const response = await withTimeout(
        fetch('/api/health', { headers: { Accept: 'application/json' } }),
        DEFAULT_TIMEOUT_MS,
    );

    const body = (await parseJson(response)) as Record<string, unknown>;
    if (!response.ok) {
        throw new Error(buildErrorMessage(response, body));
    }

    return { status: String(body.status ?? 'ok') };
}

export async function getFlows(): Promise<Flow[]> {
    const response = await withTimeout(
        fetch('/api/flows', { headers: { Accept: 'application/json' } }),
        DEFAULT_TIMEOUT_MS,
    );

    const body = (await parseJson(response)) as Record<string, unknown>;
    if (!response.ok) {
        throw new Error(buildErrorMessage(response, body));
    }

    return Array.isArray(body) ? body.map(toFlow) : (body.data as unknown[] ?? []).map(toFlow);
}

export async function createExecution(
    launcherSlug: string,
    sourceUrl: string,
    apiKey: string,
): Promise<CreateExecutionResponse> {
    const provider: { id: string; api_key?: string } = { id: 'openai' };
    if (apiKey.trim()) {
        provider.api_key = apiKey.trim();
    }

    const response = await withTimeout(
        fetch('/api/executions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ launcher: launcherSlug, source_url: sourceUrl.trim(), provider }),
        }),
        DEFAULT_TIMEOUT_MS,
    );

    const body = (await parseJson(response)) as Record<string, unknown>;
    if (!response.ok) {
        throw new Error(buildErrorMessage(response, body));
    }

    return {
        id: String(body.id ?? ''),
        status: String(body.status ?? ''),
        message: String(body.message ?? ''),
    };
}

export async function getExecution(id: string): Promise<Execution> {
    const response = await withTimeout(
        fetch(`/api/executions/${encodeURIComponent(id)}`, { headers: { Accept: 'application/json' } }),
        DEFAULT_TIMEOUT_MS,
    );

    const body = (await parseJson(response)) as Record<string, unknown>;
    if (!response.ok) {
        throw new Error(buildErrorMessage(response, body));
    }

    return toExecution('data' in body ? body.data : body);
}

interface SubscribeCallbacks {
    onSnapshot: (snapshot: Execution) => void;
    onTerminal: (snapshot: Execution, type: 'completed' | 'failed') => void;
    onError?: (error: Error) => void;
}

export function subscribeToExecution(
    id: string,
    callbacks: SubscribeCallbacks,
): () => void {
    const { onSnapshot, onTerminal, onError } = callbacks;
    let lastSnapshot: Execution | null = null;
    let cancelled = false;

    function handleSnapshot(snapshot: Execution, isTerminal: 'completed' | 'failed' | null): void {
        if (cancelled) {
            return;
        }

        if (
            lastSnapshot !== null
            && lastSnapshot.status === snapshot.status
            && JSON.stringify(lastSnapshot) === JSON.stringify(snapshot)
        ) {
            if (isTerminal) {
                onTerminal(snapshot, isTerminal);
                stop();
            }
            return;
        }

        lastSnapshot = snapshot;
        onSnapshot(snapshot);

        if (isTerminal) {
            onTerminal(snapshot, isTerminal);
            stop();
        }
    }

    let pollTimer: ReturnType<typeof setInterval> | null = null;
    let connectTimer: ReturnType<typeof setTimeout> | null = null;
    let sseAttempts = 0;
    const maxSseAttempts = 5;
    let source: EventSource | null = null;

    async function fetchFinalState(): Promise<Execution> {
        const snapshot = await getExecution(id);
        return snapshot;
    }

    function startPolling(): void {
        if (cancelled || pollTimer !== null) {
            return;
        }

        pollTimer = setInterval(async () => {
            if (cancelled) {
                return;
            }

            try {
                const snapshot = await getExecution(id);
                const isTerminal = snapshot.status === 'completed' || snapshot.status === 'failed'
                    ? snapshot.status
                    : null;
                handleSnapshot(snapshot, isTerminal);
            } catch {
                // Keep polling through transient API failures.
            }
        }, 1500);
    }

    function stopPolling(): void {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function stopSse(): void {
        if (source !== null) {
            source.close();
            source = null;
        }
    }

    function stop(): void {
        cancelled = true;
        stopSse();
        stopPolling();
        if (connectTimer !== null) {
            clearTimeout(connectTimer);
            connectTimer = null;
        }
    }

    function connectSse(): void {
        if (cancelled) {
            return;
        }

        if (typeof EventSource === 'undefined') {
            startPolling();
            return;
        }

        if (sseAttempts >= maxSseAttempts) {
            startPolling();
            return;
        }

        sseAttempts += 1;

        try {
            source = new EventSource(`/api/executions/${encodeURIComponent(id)}/stream`);

            source.addEventListener('progress', (event) => handleSseEvent(event, 'progress'));
            source.addEventListener('completed', (event) => handleSseEvent(event, 'completed'));
            source.addEventListener('failed', (event) => handleSseEvent(event, 'failed'));

            source.onopen = () => {
                sseAttempts = 0;
            };

            source.onerror = () => {
                stopSse();
                const delay = Math.min(1000 * 2 ** (sseAttempts - 1), 5000);
                connectTimer = setTimeout(() => {
                    connectTimer = null;
                    connectSse();
                }, delay);
            };
        } catch (error) {
            if (error instanceof Error) {
                onError?.(error);
            }
            startPolling();
        }
    }

    function handleSseEvent(event: MessageEvent, type: 'progress' | 'completed' | 'failed'): void {
        try {
            const data = JSON.parse(event.data) as unknown;
            const snapshot = toExecution(data);
            const isTerminal = type === 'completed' || type === 'failed' ? type : null;

            if (isTerminal) {
                fetchFinalState().then((final) => {
                    handleSnapshot(final, isTerminal);
                }).catch(() => {
                    handleSnapshot(snapshot, isTerminal);
                });
                return;
            }

            handleSnapshot(snapshot, null);
        } catch (error) {
            if (error instanceof Error) {
                onError?.(error);
            }
        }
    }

    connectSse();

    return stop;
}

export function shareRunUrl(runId: string): string {
    return `${window.location.origin}/runs/${runId}`;
}

export function parseGithubRepo(url: string): string | null {
    const trimmed = url.trim();
    const match = trimmed.match(/github\.com\/([^/]+)\/([^/#?]+)/i);
    if (!match) {
        return null;
    }
    return `${match[1]}/${match[2].replace(/\.git$/, '')}`;
}

export function isValidGithubUrl(url: string): boolean {
    return /^https:\/\/(?:www\.)?github\.com\/[^/\s]+\/[^/\s]+/i.test(url.trim());
}
