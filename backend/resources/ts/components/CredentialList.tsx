import type { ProviderCredential } from "../services/auth.ts";

export interface CredentialListProps {
    loading: boolean;
    credentials: ProviderCredential[];
    verifyResults: Record<string, string>;
    onVerify: (id: string) => void;
    onDelete: (id: string) => void;
}

export function CredentialList({
    loading,
    credentials,
    verifyResults,
    onVerify,
    onDelete,
}: CredentialListProps) {
    if (loading) {
        return <p>Loading credentials…</p>;
    }

    if (credentials.length === 0) {
        return (
            <p className="empty-state">No API keys saved yet. Add one to use your own provider.</p>
        );
    }

    return (
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
                        <button type="button" onClick={() => onVerify(c.id)}>
                            Verify
                        </button>
                        <button type="button" className="danger" onClick={() => onDelete(c.id)}>
                            Delete
                        </button>
                    </div>
                </li>
            ))}
        </ul>
    );
}
