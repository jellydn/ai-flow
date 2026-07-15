import { type FormEvent, useCallback, useEffect, useState } from "react";
import {
    deleteLauncherPrompt,
    fetchLauncherPrompts,
    type LauncherPromptEntry,
    upsertLauncherPrompt,
} from "../services/auth.ts";
import { logger } from "../lib/logger.ts";

export function WorkflowPromptsSection() {
    const [entries, setEntries] = useState<LauncherPromptEntry[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [savingSlug, setSavingSlug] = useState<string | null>(null);
    const [drafts, setDrafts] = useState<Record<string, string>>({});

    const load = useCallback(async () => {
        try {
            const data = await fetchLauncherPrompts();
            setEntries(data);
            setDrafts(
                Object.fromEntries(
                    data.map((e) => [
                        e.slug,
                        e.override_prompt_template ?? e.default_prompt_template,
                    ]),
                ),
            );
            setError("");
        } catch (e) {
            setError(e instanceof Error ? e.message : "Could not load workflow prompts.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const handleSave = async (e: FormEvent, slug: string) => {
        e.preventDefault();
        const text = (drafts[slug] ?? "").trim();
        if (text.length < 20) {
            setError("Prompt must be at least 20 characters.");
            return;
        }
        setSavingSlug(slug);
        setError("");
        try {
            await upsertLauncherPrompt(slug, text);
            await load();
        } catch (err) {
            logger.warn("Workflow prompt save failed", err);
            setError(err instanceof Error ? err.message : "Could not save prompt.");
        } finally {
            setSavingSlug(null);
        }
    };

    const handleReset = async (slug: string) => {
        if (
            !confirm(
                "Reset this workflow prompt to the platform default? Your custom text will be removed.",
            )
        ) {
            return;
        }
        setSavingSlug(slug);
        setError("");
        try {
            await deleteLauncherPrompt(slug);
            await load();
        } catch (err) {
            logger.warn("Workflow prompt reset failed", err);
            setError(err instanceof Error ? err.message : "Could not reset prompt.");
        } finally {
            setSavingSlug(null);
        }
    };

    if (loading) {
        return <p className="workflow-prompts-loading">Loading workflow prompts…</p>;
    }

    return (
        <section className="workflow-prompts" aria-labelledby="workflow-prompts-heading">
            <div className="settings-header">
                <h3 id="workflow-prompts-heading">Workflow prompts</h3>
            </div>
            <p className="workflow-prompts-hint">
                Customize instructions for each launcher. Your text is saved when you run a workflow
                and stays on that run even if you change it later.
            </p>
            {error ? <p className="settings-error">{error}</p> : null}
            <ul className="workflow-prompts-list">
                {entries.map((entry) => (
                    <li key={entry.slug} className="workflow-prompt-card">
                        <h4>{entry.name}</h4>
                        {entry.uses_override ? (
                            <span className="workflow-prompt-badge">Custom</span>
                        ) : null}
                        <form onSubmit={(e) => handleSave(e, entry.slug)}>
                            <textarea
                                rows={6}
                                value={drafts[entry.slug] ?? ""}
                                onChange={(ev) =>
                                    setDrafts((prev) => ({
                                        ...prev,
                                        [entry.slug]: ev.target.value,
                                    }))
                                }
                                aria-label={`Prompt for ${entry.name}`}
                            />
                            <div className="workflow-prompt-actions">
                                <button type="submit" disabled={savingSlug === entry.slug}>
                                    {savingSlug === entry.slug ? "Saving…" : "Save"}
                                </button>
                                {entry.uses_override ? (
                                    <button
                                        type="button"
                                        disabled={savingSlug === entry.slug}
                                        onClick={() => handleReset(entry.slug)}
                                    >
                                        Reset to default
                                    </button>
                                ) : null}
                            </div>
                        </form>
                    </li>
                ))}
            </ul>
        </section>
    );
}
