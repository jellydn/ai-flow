import { useEffect, useRef, useState } from 'react';
import type { Run } from '../types/api.ts';
import { decodeRun, fetchRun } from '../services/run.ts';

const POLL_INTERVAL_MS = 1500;

export function useRunSubscription(runId: string | null, initialRun?: Run | null) {
    const [run, setRun] = useState<Run | null>(initialRun ?? null);
    const [error, setError] = useState<string | null>(null);
    const initialRunRef = useRef(initialRun ?? null);

    initialRunRef.current = initialRun ?? null;

    useEffect(() => {
        setError(null);

        if (!runId) {
            setRun(null);
            return;
        }

        const id = runId;
        setRun(initialRunRef.current);

        let cancelled = false;
        let source: EventSource | null = null;
        let pollTimer: ReturnType<typeof setInterval> | null = null;
        let terminal = false;

        function stopSse() {
            if (source) {
                source.close();
                source = null;
            }
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function stop() {
            stopSse();
            stopPolling();
        }

        function startPolling() {
            if (pollTimer || cancelled) {
                return;
            }

            pollTimer = setInterval(async () => {
                if (cancelled) {
                    return;
                }

                try {
                    const snapshot = await fetchRun(id);
                    if (cancelled) {
                        return;
                    }
                    setRun(snapshot);
                    if (snapshot.status === 'completed' || snapshot.status === 'failed') {
                        terminal = true;
                        stop();
                    }
                } catch {
                    // Keep polling until the run resolves or the hook unmounts.
                }
            }, POLL_INTERVAL_MS);
        }

        function handleEvent(event: MessageEvent, type: 'progress' | 'completed' | 'failed') {
            try {
                const snapshot = decodeRun(JSON.parse(event.data) as unknown);
                if (cancelled) {
                    return;
                }
                setRun(snapshot);
                if (type === 'completed' || type === 'failed') {
                    terminal = true;
                    stop();
                }
            } catch (e) {
                if (e instanceof Error) {
                    setError(e.message);
                }
            }
        }

        function connectSse() {
            if (cancelled || typeof EventSource === 'undefined') {
                startPolling();
                return;
            }

            try {
                source = new EventSource(`/api/runs/${encodeURIComponent(id)}/stream`);
                source.addEventListener('progress', (event) => handleEvent(event, 'progress'));
                source.addEventListener('completed', (event) => handleEvent(event, 'completed'));
                source.addEventListener('failed', (event) => handleEvent(event, 'failed'));
                source.onerror = () => {
                    stopSse();
                    if (!terminal && !cancelled) {
                        startPolling();
                    }
                };
            } catch {
                if (!cancelled) {
                    startPolling();
                }
            }
        }

        connectSse();

        return () => {
            cancelled = true;
            stop();
        };
    }, [runId]);

    useEffect(() => {
        if (runId && initialRunRef.current) {
            setRun((current) => current ?? initialRunRef.current);
        }
    }, [runId, initialRun]);

    return { run, error };
}
