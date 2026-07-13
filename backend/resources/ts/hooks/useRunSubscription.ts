import { useEffect, useState } from "react";
import type { Run } from "../types/api.ts";
import { decodeRun, fetchRun } from "../services/run.ts";
import { logger } from "../lib/logger.ts";

const POLL_INTERVAL_MS = 1500;

function seedRunForId(runId: string | null, initialRun?: Run | null): Run | null {
    if (!runId) {
        return null;
    }

    return initialRun?.id === runId ? initialRun : null;
}

function syncKey(runId: string | null, initialRun?: Run | null): string {
    return `${runId ?? ""}|${initialRun?.id ?? ""}`;
}

export function useRunSubscription(runId: string | null, initialRun?: Run | null) {
    const [run, setRun] = useState<Run | null>(() => seedRunForId(runId, initialRun));
    const [error, setError] = useState<string | null>(null);
    const [appliedSyncKey, setAppliedSyncKey] = useState(() => syncKey(runId, initialRun));

    const nextSyncKey = syncKey(runId, initialRun);
    if (nextSyncKey !== appliedSyncKey) {
        setAppliedSyncKey(nextSyncKey);
        setRun(seedRunForId(runId, initialRun));
    }

    useEffect(() => {
        setError(null);

        if (!runId) {
            return undefined;
        }

        const id = runId;
        let cancelled = false;
        let source: EventSource | null = null;
        let pollTimer: ReturnType<typeof setInterval> | null = null;
        let terminal = false;

        function stopSse(): void {
            if (source) {
                source.close();
                source = null;
            }
        }

        function stopPolling(): void {
            if (pollTimer !== null) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        const pollTick = () => {
            void (async () => {
                if (cancelled) {
                    return;
                }

                try {
                    const snapshot = await fetchRun(id);
                    if (cancelled) {
                        return;
                    }
                    setRun(snapshot);
                    if (snapshot.status === "completed" || snapshot.status === "failed") {
                        terminal = true;
                        stopPolling();
                        stopSse();
                    }
                } catch (err) {
                    logger.warn("Polling failed for run", id, err);
                    // Keep polling until the run resolves or the hook unmounts.
                }
            })();
        };

        function handleEvent(event: MessageEvent, type: "progress" | "completed" | "failed"): void {
            try {
                const snapshot = decodeRun(JSON.parse(event.data) as unknown);
                if (cancelled) {
                    return;
                }
                setRun(snapshot);
                if (type === "completed" || type === "failed") {
                    terminal = true;
                    stopPolling();
                    stopSse();
                }
            } catch (e) {
                logger.warn("Failed to handle SSE event for run", id, e);
                if (e instanceof Error) {
                    setError(e.message);
                }
            }
        }

        if (typeof EventSource === "undefined") {
            pollTimer = setInterval(pollTick, POLL_INTERVAL_MS);
        } else {
            const onProgress = (event: Event) => handleEvent(event as MessageEvent, "progress");
            const onCompleted = (event: Event) => handleEvent(event as MessageEvent, "completed");
            const onFailed = (event: Event) => handleEvent(event as MessageEvent, "failed");
            const onStreamError = () => {
                stopSse();
                if (!terminal && !cancelled && pollTimer === null) {
                    pollTimer = setInterval(pollTick, POLL_INTERVAL_MS);
                }
            };

            try {
                source = new EventSource(`/api/runs/${encodeURIComponent(id)}/stream`);
                source.addEventListener("progress", onProgress);
                source.addEventListener("completed", onCompleted);
                source.addEventListener("failed", onFailed);
                source.onerror = onStreamError;
            } catch (e) {
                logger.warn("EventSource unavailable, falling back to polling for run", id, e);
                pollTimer = setInterval(pollTick, POLL_INTERVAL_MS);
            }

            return () => {
                cancelled = true;
                if (pollTimer !== null) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
                if (source) {
                    source.removeEventListener("progress", onProgress);
                    source.removeEventListener("completed", onCompleted);
                    source.removeEventListener("failed", onFailed);
                    source.onerror = null;
                    source.close();
                    source = null;
                }
            };
        }

        return () => {
            cancelled = true;
            if (pollTimer !== null) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        };
    }, [runId]);

    return { run, error };
}
