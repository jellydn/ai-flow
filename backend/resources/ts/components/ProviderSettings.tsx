import { type FormEvent, useCallback, useEffect, useState } from "react";
import {
    createCredential,
    deleteCredential,
    fetchCredentials,
    fetchProviders,
    verifyCredential,
    type ProviderCredential,
} from "../services/auth.ts";

export function ProviderSettings() {
    const [credentials, setCredentials] = useState<ProviderCredential[]>([]);
    const [providers, setProviders] = useState<{ id: string; name: string }[]>([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [error, setError] = useState("");

    const [provider, setProvider] = useState("openai");
    const [label, setLabel] = useState("");
    const [apiKey, setApiKey] = useState("");
    const [submitting, setSubmitting] = useState(false);

    const [verifyResults, setVerifyResults] = useState<Record<string, string>>({});

    const load = useCallback(async () => {
        try {
            const [creds, provs] = await Promise.all([fetchCredentials(), fetchProviders()]);
            setCredentials(creds);
            setProviders(provs);
        } catch (e) {
            setError(e instanceof Error ? e.message : "Could not load credentials.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const handleCreate = async (e: FormEvent) => {
        e.preventDefault();
        if (!label.trim() || !apiKey.trim()) {
            setError("Label and API key are required.");
            return;
        }
        setSubmitting(true);
        setError("");
        try {
            await createCredential({ provider, label: label.trim(), api_key: apiKey.trim() });
            setShowForm(false);
            setLabel("");
            setApiKey("");
            await load();
        } catch (e) {
            setError(e instanceof Error ? e.message : "Could not save credential.");
        } finally {
            setSubmitting(false);
        }
    };

    const handleVerify = async (id: string) => {
        try {
            const result = await verifyCredential(id);
            setVerifyResults((prev) => ({ ...prev, [id]: result.message }));
        } catch {
            setVerifyResults((prev) => ({ ...prev, [id]: "Verification failed." }));
        }
    };

    const handleDelete = async (id: string) => {
        if (!confirm("Delete this API key? This cannot be undone.")) return;
        try {
            await deleteCredential(id);
            setCredentials((prev) => prev.filter((c) => c.id !== id));
        } catch {
            setError("Could not delete credential.");
        }
    };

    return (
        <div className="provider-settings">
            <div className="settings-header">
                <h3>Your API Keys</h3>
                <button type="button" onClick={() => setShowForm(!showForm)}>
                    {showForm ? "Cancel" : "Add key"}
                </button>
            </div>

            {showForm && (
                <form className="credential-form" onSubmit={handleCreate}>
                    <select value={provider} onChange={(e) => setProvider(e.target.value)}>
                        {providers.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                    <input
                        type="text"
                        value={label}
                        onChange={(e) => setLabel(e.target.value)}
                        placeholder="Label (e.g. Personal OpenAI)"
                    />
                    <input
                        type="password"
                        value={apiKey}
                        onChange={(e) => setApiKey(e.target.value)}
                        placeholder="API key"
                        autoComplete="off"
                    />
                    {error && <p className="auth-error">{error}</p>}
                    <button type="submit" disabled={submitting}>
                        {submitting ? "Saving…" : "Save"}
                    </button>
                </form>
            )}

            {loading ? (
                <p>Loading credentials…</p>
            ) : credentials.length === 0 ? (
                <p className="empty-state">No API keys saved yet. Add one to use your own provider.</p>
            ) : (
                <ul className="credential-list">
                    {credentials.map((c) => (
                        <li key={c.id} className="credential-item">
                            <div>
                                <strong>{c.label}</strong>
                                <span className="provider-tag">{c.provider}</span>
                                {c.is_default && <span className="default-tag">default</span>}
                                <code>{c.masked_key}</code>
                                {verifyResults[c.id] && (
                                    <p className="verify-result">{verifyResults[c.id]}</p>
                                )}
                            </div>
                            <div className="credential-actions">
                                <button type="button" onClick={() => handleVerify(c.id)}>
                                    Verify
                                </button>
                                <button type="button" className="danger" onClick={() => handleDelete(c.id)}>
                                    Delete
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <p className="privacy-note">
                Your API keys are encrypted before storage. They are decrypted only when sending a request to your provider.
            </p>
        </div>
    );
}
