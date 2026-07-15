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

    const hintId = "workflow-prompts-hint";

    return (
        <section className="workflow-prompts" aria-labelledby="workflow-prompts-heading">
            <div className="settings-header workflow-prompts-header">
                <h3 id="workflow-prompts-heading">Workflow prompts</h3>
            </div>
            <p id={hintId} className="workflow-prompts-hint">
                Customize instructions for each launcher. Your text is saved when you run a workflow
                and stays on that run even if you change it later.
            </p>
            {error ? (
                <p className="auth-error workflow-prompts-error" role="alert">
                    {error}
                </p>
            ) : null}
            <ul className="workflow-prompts-list">
                {entries.map((entry) => {
                    const fieldId = `workflow-prompt-${entry.slug}`;
                    return (
                        <li key={entry.slug} className="workflow-prompt-card">
                            <form
                                className="workflow-prompt-form"
                                onSubmit={(e) => handleSave(e, entry.slug)}
                            >
                                <fieldset className="workflow-prompt-fieldset">
                                    <legend className="workflow-prompt-legend">
                                        <span className="workflow-prompt-title">{entry.name}</span>
                                        {entry.uses_override ? (
                                            <span className="workflow-prompt-badge">Custom</span>
                                        ) : null}
                                    </legend>
                                    <label className="workflow-prompt-label" htmlFor={fieldId}>
                                        Instructions sent to the AI for this workflow
                                    </label>
                                    <textarea
                                        id={fieldId}
                                        name={`prompt_${entry.slug}`}
                                        className="workflow-prompt-textarea"
                                        rows={6}
                                        value={drafts[entry.slug] ?? ""}
                                        onChange={(ev) =>
                                            setDrafts((prev) => ({
                                                ...prev,
                                                [entry.slug]: ev.target.value,
                                            }))
                                        }
                                        aria-describedby={hintId}
                                    />
                                    <div className="workflow-prompt-actions">
                                        <button
                                            type="submit"
                                            className="workflow-prompt-save"
                                            disabled={savingSlug === entry.slug}
                                        >
                                            {savingSlug === entry.slug ? "Saving…" : "Save"}
                                        </button>
                                        {entry.uses_override ? (
                                            <button
                                                type="button"
                                                className="workflow-prompt-reset"
                                                disabled={savingSlug === entry.slug}
                                                onClick={() => handleReset(entry.slug)}
                                            >
                                                Reset to default
                                            </button>
                                        ) : null}
                                    </div>
                                </fieldset>
                            </form>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
