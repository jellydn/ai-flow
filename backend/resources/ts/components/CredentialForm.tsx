import type { FormEvent } from "react";

export interface CredentialFormProps {
    provider: string;
    setProvider: (v: string) => void;
    label: string;
    setLabel: (v: string) => void;
    apiKey: string;
    setApiKey: (v: string) => void;
    submitting: boolean;
    error: string;
    onSubmit: (e: FormEvent) => void;
    providers: { id: string; name: string }[];
}

export function CredentialForm({
    provider,
    setProvider,
    label,
    setLabel,
    apiKey,
    setApiKey,
    submitting,
    error,
    onSubmit,
    providers,
}: CredentialFormProps) {
    return (
        <form className="credential-form" onSubmit={onSubmit}>
            <select value={provider} onChange={(e) => setProvider(e.target.value)}>
                {providers.map((p) => (
                    <option key={p.id} value={p.id}>
                        {p.name}
                    </option>
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
    );
}
