import { ArrowRight, Clock3, ShieldCheck, Zap } from "lucide-react";
import { aiLauncherTools } from "../data/aiLauncherConfig.ts";

interface LaunchAreaProps {
    selectedTool: string;
    setSelectedTool: (tool: string) => void;
    apiKey: string;
    setApiKey: (key: string) => void;
    launch: () => void;
    isLaunching: boolean;
}

export function LaunchArea({
    selectedTool,
    setSelectedTool,
    apiKey,
    setApiKey,
    launch,
    isLaunching,
}: LaunchAreaProps) {
    const activeTool = aiLauncherTools.find((tool) => tool.name === selectedTool) ?? aiLauncherTools[0];

    return (
        <>
            <div className="provider-section">
                <div className="provider-heading">
                    <strong>AI tool</strong>
                    <span>Optional</span>
                </div>
                <div className="provider-fields">
                    <label>
                        <span>Tool</span>
                        <select
                            value={selectedTool}
                            onChange={(event) => setSelectedTool(event.target.value)}
                            aria-label="AI tool"
                        >
                            {aiLauncherTools.map((tool) => (
                                <option key={tool.name} value={tool.name}>
                                    {tool.name}
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
                <p>{activeTool?.description ?? "Use your own API key for this run."}</p>
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