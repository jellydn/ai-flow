import { ArrowRight, Clock3, ShieldCheck, Zap } from "lucide-react";
import type { RunProviderId } from "../services/run.ts";

const runProviders: { id: RunProviderId; name: string }[] = [
    { id: "openai", name: "OpenAI" },
    { id: "openrouter", name: "OpenRouter" },
];

interface LaunchAreaProps {
    provider: RunProviderId;
    setProvider: (provider: RunProviderId) => void;
    apiKey: string;
    setApiKey: (key: string) => void;
    launch: () => void;
    isLaunching: boolean;
}

export function LaunchArea({
    provider,
    setProvider,
    apiKey,
    setApiKey,
    launch,
    isLaunching,
}: LaunchAreaProps) {
    return (
        <>
            <div className="provider-section">
                <div className="provider-heading">
                    <strong>AI Provider</strong>
                    <span>Optional</span>
                </div>
                <div className="provider-fields">
                    <label>
                        <span>Provider</span>
                        <select
                            value={provider}
                            onChange={(event) => setProvider(event.target.value as RunProviderId)}
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
                <p>
                    Use your own API key to execute this workflow. It is used only for this
                    execution.
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
