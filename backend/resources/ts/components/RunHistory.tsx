import { useCallback, useEffect, useState } from "react";
import { deleteRun, fetchUserRuns, retryRun } from "../services/auth.ts";
import type { Run } from "../types/api.ts";
import { decodeRun } from "../services/run.ts";

const STATUS_OPTIONS = ["", "completed", "failed", "queued", "running"] as const;

export function RunHistory() {
    const [runs, setRuns] = useState<Run[]>([]);
    const [loading, setLoading] = useState(true);
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");

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

    const handleRetry = async (id: string) => {
        try {
            await retryRun(id);
            load();
        } catch {
            setError("Could not retry run.");
        }
    };

    const handleDelete = async (id: string) => {
        if (!confirm("Delete this run?")) return;
        try {
            await deleteRun(id);
            setRuns((prev) => prev.filter((r) => r.id !== id));
        } catch {
            setError("Could not delete run.");
        }
    };

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
                <p className="empty-state">No runs yet. Launch a workflow from the home page.</p>
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
                                    onClick={() => window.open(`/runs/${run.id}`, "_blank")}
                                >
                                    Open
                                </button>
                                {(run.status === "completed" || run.status === "failed") && (
                                    <button type="button" onClick={() => handleRetry(run.id)}>
                                        Retry
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className="danger"
                                    onClick={() => handleDelete(run.id)}
                                >
                                    Delete
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
