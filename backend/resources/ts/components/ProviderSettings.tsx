import { type FormEvent, useCallback, useEffect, useState } from "react";
import {
    createCredential,
    deleteCredential,
    fetchCredentials,
    fetchProviders,
    verifyCredential,
    type ProviderCredential,
} from "../services/auth.ts";
import { CredentialForm } from "./CredentialForm.tsx";
import { CredentialList } from "./CredentialList.tsx";
import { PrivacyNote } from "./PrivacyNote.tsx";

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

    useEffect(() => {
        load();
    }, [load]);

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
                <CredentialForm
                    provider={provider}
                    setProvider={setProvider}
                    label={label}
                    setLabel={setLabel}
                    apiKey={apiKey}
                    setApiKey={setApiKey}
                    submitting={submitting}
                    error={error}
                    onSubmit={handleCreate}
                    providers={providers}
                />
            )}

            <CredentialList
                loading={loading}
                credentials={credentials}
                verifyResults={verifyResults}
                onVerify={handleVerify}
                onDelete={handleDelete}
            />

            <PrivacyNote />
        </div>
    );
}
