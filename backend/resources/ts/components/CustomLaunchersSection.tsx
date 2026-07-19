import { type FormEvent, useCallback, useEffect, useState } from "react";
import {
    createUserLauncher,
    deleteUserLauncher,
    fetchUserLaunchers,
    updateUserLauncher,
} from "../services/userLaunchers.ts";
import type { UserLauncher } from "../types/api.ts";
import { logger } from "../lib/logger.ts";

const INPUT_TYPES = [
    { value: "repository", label: "Repository" },
    { value: "pull_request", label: "Pull Request" },
    { value: "issue", label: "Issue" },
] as const;

const DEFAULT_OUTPUT_SCHEMA = JSON.stringify(
    {
        type: "object",
        additionalProperties: false,
        required: ["summary", "risk", "findings", "verification_steps"],
        properties: {
            summary: { type: "string" },
            risk: { type: "string", enum: ["low", "medium", "high", "critical"] },
            findings: {
                type: "array",
                items: {
                    type: "object",
                    additionalProperties: false,
                    required: ["severity", "title", "description", "recommendation"],
                    properties: {
                        severity: {
                            type: "string",
                            enum: ["info", "low", "medium", "high", "critical"],
                        },
                        title: { type: "string" },
                        description: { type: "string" },
                        recommendation: { type: "string" },
                    },
                },
            },
            verification_steps: {
                type: "array",
                items: { type: "string" },
            },
        },
    },
    null,
    2,
);

export function CustomLaunchersSection() {
    const [launchers, setLaunchers] = useState<UserLauncher[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    // Form fields
    const [slug, setSlug] = useState("");
    const [name, setName] = useState("");
    const [description, setDescription] = useState("");
    const [promptTemplate, setPromptTemplate] = useState("");
    const [inputType, setInputType] = useState("repository");
    const [outputSchema, setOutputSchema] = useState(DEFAULT_OUTPUT_SCHEMA);

    const load = useCallback(async () => {
        try {
            const data = await fetchUserLaunchers();
            setLaunchers(data);
            setError("");
        } catch (e) {
            setError(e instanceof Error ? e.message : "Could not load custom launchers.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const resetForm = () => {
        setSlug("");
        setName("");
        setDescription("");
        setPromptTemplate("");
        setInputType("repository");
        setOutputSchema(DEFAULT_OUTPUT_SCHEMA);
        setEditingId(null);
        setShowForm(false);
    };

    const validateOutputSchema = (raw: string): boolean => {
        try {
            JSON.parse(raw);
            return true;
        } catch {
            return false;
        }
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setError("");

        if (!validateOutputSchema(outputSchema)) {
            setError("Output schema must be valid JSON.");
            return;
        }

        setSaving(true);
        try {
            if (editingId) {
                await updateUserLauncher(editingId, {
                    name,
                    description,
                    prompt_template: promptTemplate,
                    input_type: inputType,
                    output_schema: outputSchema,
                });
            } else {
                await createUserLauncher({
                    slug,
                    name,
                    description,
                    prompt_template: promptTemplate,
                    input_type: inputType,
                    output_schema: outputSchema,
                });
            }
            resetForm();
            await load();
        } catch (err) {
            logger.warn("Custom launcher save failed", err);
            setError(err instanceof Error ? err.message : "Could not save launcher.");
        } finally {
            setSaving(false);
        }
    };

    const handleEdit = (launcher: UserLauncher) => {
        setEditingId(launcher.id);
        setSlug(launcher.slug);
        setName(launcher.name);
        setDescription(launcher.description);
        setPromptTemplate(launcher.prompt_template);
        setInputType(launcher.input_type);
        setOutputSchema(JSON.stringify(launcher.output_schema, null, 2));
        setShowForm(true);
    };

    const handleDelete = async (launcher: UserLauncher) => {
        if (
            !confirm(
                `Delete "${launcher.name}"? All runs using this launcher will also be deleted. This cannot be undone.`,
            )
        ) {
            return;
        }
        setError("");
        try {
            await deleteUserLauncher(launcher.id);
            await load();
        } catch (err) {
            logger.warn("Custom launcher delete failed", err);
            setError(err instanceof Error ? err.message : "Could not delete launcher.");
        }
    };

    if (loading) {
        return <p className="workflow-prompts-loading">Loading custom launchers…</p>;
    }

    return (
        <section className="workflow-prompts" aria-labelledby="custom-launchers-heading">
            <div className="settings-header workflow-prompts-header">
                <h3 id="custom-launchers-heading">Custom launchers</h3>
                <button
                    type="button"
                    className="workflow-prompt-save"
                    onClick={() => {
                        resetForm();
                        setShowForm(!showForm);
                    }}
                >
                    {showForm ? "Cancel" : "+ New launcher"}
                </button>
            </div>
            <p className="workflow-prompts-hint">
                Create your own AI workflows with custom prompts and output schemas. Custom
                launchers appear alongside built-in ones on the home page.
            </p>

            {error ? (
                <p className="auth-error workflow-prompts-error" role="alert">
                    {error}
                </p>
            ) : null}

            {showForm && (
                <form className="custom-launcher-form" onSubmit={handleSubmit}>
                    <div className="custom-launcher-form-grid">
                        <label className="form-field">
                            <span>Slug</span>
                            <input
                                type="text"
                                value={slug}
                                onChange={(e) => setSlug(e.target.value)}
                                required
                                pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$"
                                placeholder="my-security-scan"
                                disabled={editingId !== null}
                                maxLength={64}
                            />
                            <span className="form-hint">
                                URL-safe identifier. Cannot be changed after creation.
                            </span>
                        </label>
                        <label className="form-field">
                            <span>Name</span>
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                required
                                placeholder="Security Scan"
                                maxLength={128}
                            />
                        </label>
                        <label className="form-field full-width">
                            <span>Description</span>
                            <input
                                type="text"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                required
                                placeholder="Scan PRs for security vulnerabilities"
                                maxLength={512}
                            />
                        </label>
                        <label className="form-field">
                            <span>Input type</span>
                            <select
                                value={inputType}
                                onChange={(e) => setInputType(e.target.value)}
                                required
                            >
                                {INPUT_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <label className="form-field">
                        <span>Prompt template</span>
                        <textarea
                            className="workflow-prompt-textarea"
                            rows={6}
                            value={promptTemplate}
                            onChange={(e) => setPromptTemplate(e.target.value)}
                            required
                            minLength={20}
                            placeholder="Review this code for security issues..."
                        />
                    </label>
                    <label className="form-field">
                        <span>Output schema (JSON Schema)</span>
                        <textarea
                            className="workflow-prompt-textarea custom-launcher-schema"
                            rows={10}
                            value={outputSchema}
                            onChange={(e) => setOutputSchema(e.target.value)}
                            required
                            spellCheck={false}
                        />
                        <span
                            className={`form-hint ${validateOutputSchema(outputSchema) ? "schema-valid" : "schema-invalid"}`}
                        >
                            {validateOutputSchema(outputSchema)
                                ? "Valid JSON"
                                : "Invalid JSON — check syntax"}
                        </span>
                    </label>
                    <div className="workflow-prompt-actions">
                        <button type="submit" className="workflow-prompt-save" disabled={saving}>
                            {saving ? "Saving…" : editingId ? "Update launcher" : "Create launcher"}
                        </button>
                        <button type="button" className="workflow-prompt-reset" onClick={resetForm}>
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            {launchers.length > 0 && (
                <ul className="workflow-prompts-list">
                    {launchers.map((launcher) => (
                        <li key={launcher.id} className="workflow-prompt-card">
                            <div className="workflow-prompt-legend">
                                <span className="workflow-prompt-title">{launcher.name}</span>
                                <span className="workflow-prompt-badge">Custom</span>
                                <span className="custom-launcher-slug">{launcher.slug}</span>
                            </div>
                            <p className="custom-launcher-desc">{launcher.description}</p>
                            <div className="workflow-prompt-actions">
                                <button
                                    type="button"
                                    className="workflow-prompt-save"
                                    onClick={() => handleEdit(launcher)}
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    className="workflow-prompt-reset"
                                    onClick={() => handleDelete(launcher)}
                                >
                                    Delete
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {!showForm && launchers.length === 0 && (
                <p className="workflow-prompts-empty">
                    No custom launchers yet. Create one to get started.
                </p>
            )}
        </section>
    );
}
