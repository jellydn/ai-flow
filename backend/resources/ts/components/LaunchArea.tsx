import { ArrowRight, Clock3, ShieldCheck, Zap } from "lucide-react";
import type { RunProviderId } from "../services/run.ts";
import type { ProviderCredential } from "../services/auth.ts";
import type { ProviderCatalogEntry } from "../lib/runModels.ts";

const runProviders: { id: RunProviderId; name: string }[] = [
    { id: "openai", name: "OpenAI" },
    { id: "openrouter", name: "OpenRouter" },
    { id: "anthropic", name: "Anthropic" },
    { id: "gemini", name: "Google Gemini" },
];

interface LaunchAreaProps {
    provider: RunProviderId;
    setProvider: (provider: RunProviderId) => void;
    model: string;
    setModel: (model: string) => void;
    providerCatalog: ProviderCatalogEntry[];
    apiKey: string;
    setApiKey: (key: string) => void;
    launch: () => void;
    isLaunching: boolean;
    /** Saved credentials for authenticated users (empty for anonymous). */
    credentials?: ProviderCredential[];
    /** Selected saved credential ID (null = use one-time key / server key). */
    selectedCredentialId?: string | null;
    setSelectedCredentialId?: (id: string | null) => void;
    /** Show step 3 label when the user is signed in (provider / own key). */
    showSignedInStep?: boolean;
    onManageApiKeys?: () => void;
}

export function LaunchArea({
    provider,
    setProvider,
    model,
    setModel,
    providerCatalog,
    apiKey,
    setApiKey,
    launch,
    isLaunching,
    credentials = [],
    selectedCredentialId = null,
    setSelectedCredentialId,
    showSignedInStep = false,
    onManageApiKeys,
}: LaunchAreaProps) {
    const hasSavedCredentials = credentials.length > 0;
    const usingSavedCredential = selectedCredentialId !== null && selectedCredentialId !== "";
    const modelOptions = providerCatalog.find((entry) => entry.id === provider)?.models ?? [];
    let providerBadge = "Optional";
    if (hasSavedCredentials) {
        providerBadge = "Saved keys";
    } else if (showSignedInStep) {
        providerBadge = "Your key or server";
    }

    return (
        <>
            {showSignedInStep && (
                <div className="step-label workflow-label" id="provider-step">
                    <span>3</span> Choose AI provider, model &amp; key
                </div>
            )}
            {showSignedInStep && (
                <p className="launch-signed-in-hint">
                    {hasSavedCredentials
                        ? "Pick a saved key below, or switch to a one-time / server key."
                        : "No saved keys yet — paste a one-time key or use the server key."}{" "}
                    {onManageApiKeys && (
                        <button type="button" className="auth-link" onClick={onManageApiKeys}>
                            Manage API keys
                        </button>
                    )}
                </p>
            )}
            <div className="provider-section" id="provider">
                <div className="provider-heading">
                    <strong>{hasSavedCredentials ? "Your API key" : "AI Provider"}</strong>
                    <span>{providerBadge}</span>
                </div>
                {hasSavedCredentials && (
                    <div className="provider-fields provider-fields-single">
                        <label>
                            <span>Saved key</span>
                            <select
                                value={selectedCredentialId ?? ""}
                                onChange={(event) => {
                                    const val = event.target.value || null;
                                    setSelectedCredentialId?.(val);
                                    // Auto-select provider to match the credential.
                                    if (val) {
                                        const cred = credentials.find((c) => c.id === val);
                                        if (
                                            cred &&
                                            (cred.provider === "openai" ||
                                                cred.provider === "openrouter" ||
                                                cred.provider === "anthropic" ||
                                                cred.provider === "gemini")
                                        ) {
                                            setProvider(cred.provider);
                                            const models =
                                                providerCatalog.find((p) => p.id === cred.provider)
                                                    ?.models ?? [];
                                            if (
                                                cred.default_model &&
                                                models.includes(cred.default_model)
                                            ) {
                                                setModel(cred.default_model);
                                            } else if (models[0]) {
                                                setModel(models[0]);
                                            }
                                        }
                                    }
                                }}
                                aria-label="Saved API credential"
                            >
                                <option value="">Use one-time key / server key</option>
                                {credentials.map((cred) => (
                                    <option key={cred.id} value={cred.id}>
                                        {cred.label} ({cred.provider}
                                        {cred.is_default ? ", default" : ""})
                                        {cred.masked_key ? ` · ${cred.masked_key}` : ""}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                )}
                {modelOptions.length > 0 && (
                    <div className="provider-fields provider-fields-single">
                        <label>
                            <span>Model</span>
                            <select
                                value={modelOptions.includes(model) ? model : modelOptions[0]}
                                onChange={(event) => setModel(event.target.value)}
                                aria-label="AI model"
                            >
                                {modelOptions.map((item) => (
                                    <option key={item} value={item}>
                                        {item}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                )}
                {!usingSavedCredential && (
                    <div className="provider-fields">
                        <label>
                            <span>Provider</span>
                            <select
                                value={provider}
                                onChange={(event) =>
                                    setProvider(event.target.value as RunProviderId)
                                }
                                aria-label="AI provider"
                            >
                                {runProviders.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span>API Key</span>
                            <input
                                type="password"
                                value={apiKey}
                                onChange={(event) => setApiKey(event.target.value)}
                                placeholder="Leave blank to use server key"
                                autoComplete="off"
                                spellCheck="false"
                            />
                        </label>
                    </div>
                )}
                <p>
                    {usingSavedCredential
                        ? "Using your saved encrypted API key. It is decrypted only for this execution."
                        : hasSavedCredentials
                          ? "One-time or server key for this run only. Choose a saved key above to use a stored key."
                          : "Use your own API key to execute this workflow. It is used only for this execution."}
                </p>
            </div>

            <button type="button" className="launch-button" onClick={launch} disabled={isLaunching}>
                <Zap size={19} fill="currentColor" />{" "}
                {isLaunching ? "Starting…" : "Launch workflow"} <ArrowRight size={19} />
            </button>
            <div className="trust-row">
                <span>
                    <ShieldCheck size={15} /> Public repositories only
                </span>
                <i />
                <span>
                    <Clock3 size={15} /> Results in under a minute
                </span>
            </div>
        </>
    );
}
