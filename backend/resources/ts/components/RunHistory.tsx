import { useCallback, useEffect, useState } from "react";
import { deleteRun, fetchUserRuns, retryRun } from "../services/auth.ts";
import type { Run } from "../types/api.ts";
import { decodeRun } from "../services/run.ts";
import { goto } from "../lib/navigate.ts";
import { logger } from "../lib/logger.ts";

interface RunHistoryProps {
    navigate: (pathname: string) => void;
}

const STATUS_OPTIONS = ["", "completed", "failed", "queued", "running"] as const;

export function RunHistory({ navigate }: RunHistoryProps) {
    const [runs, setRuns] = useState<Run[]>([]);
    const [loading, setLoading] = useState(true);
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [actioningId, setActioningId] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (status) params.status = status;
            const body = (await fetchUserRuns(params)) as { data: unknown[] };
            setRuns((body.data ?? []).map(decodeRun));
        } catch (e) {
            setError(e instanceof Error ? e.message : "Could not load runs.");
        } finally {
            setLoading(false);
        }
    }, [status]);

    useEffect(() => {
        load();
    }, [load]);

    /**
     * Wrap an async action with loading/idle state management and error handling.
     * Extracts the duplicated try/catch/finally pattern shared by retry and delete.
     */
    const withAction = useCallback(
        async (id: string, fn: () => Promise<void>, errorMsg: string) => {
            setActioningId(id);
            try {
                await fn();
            } catch (err) {
                logger.warn("Run action failed", err);
                setError(errorMsg);
            } finally {
                setActioningId(null);
            }
        },
        [],
    );

    const handleRetry = useCallback(
        (id: string) => {
            withAction(id, () => retryRun(id).then(() => load()), "Could not retry run.");
        },
        [load, withAction],
    );

    const handleDelete = useCallback(
        (id: string) => {
            if (!confirm("Delete this run?")) return;
            withAction(
                id,
                () => deleteRun(id).then(() => setRuns((prev) => prev.filter((r) => r.id !== id))),
                "Could not delete run.",
            );
        },
        [withAction],
    );

    return (
        <div className="run-history">
            <div className="history-filters">
                <select value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="">All statuses</option>
                    {STATUS_OPTIONS.filter(Boolean).map((s) => (
                        <option key={s} value={s}>
                            {s}
                        </option>
                    ))}
                </select>
            </div>
            {error && <p className="auth-error">{error}</p>}
            {loading ? (
                <p>Loading runs…</p>
            ) : runs.length === 0 ? (
                <div className="empty-state">
                    <p>
                        No runs in your history yet. Workflows launched while signed in appear here
                        (anonymous launches on the home page are not listed).
                    </p>
                    <button
                        type="button"
                        className="launch-button"
                        onClick={() => goto("/", navigate)}
                    >
                        Go to home and launch
                    </button>
                </div>
            ) : (
                <ul className="run-list">
                    {runs.map((run) => (
                        <li key={run.id} className="run-item">
                            <div className="run-meta">
                                <span className={`status-badge ${run.status}`}>{run.status}</span>
                                <span className="run-launcher">{run.launcher ?? "—"}</span>
                                <span className="run-date">
                                    {run.created_at
                                        ? new Date(run.created_at).toLocaleDateString()
                                        : "—"}
                                </span>
                            </div>
                            <div className="run-actions">
                                <button
                                    type="button"
                                    onClick={() => goto(`/runs/${run.id}`, navigate)}
                                    disabled={actioningId !== null}
                                >
                                    Open
                                </button>
                                {(run.status === "completed" || run.status === "failed") && (
                                    <button
                                        type="button"
                                        onClick={() => handleRetry(run.id)}
                                        disabled={actioningId !== null}
                                    >
                                        {actioningId === run.id ? "Retrying…" : "Retry"}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className="danger"
                                    onClick={() => handleDelete(run.id)}
                                    disabled={actioningId !== null}
                                >
                                    {actioningId === run.id ? "Deleting…" : "Delete"}
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
