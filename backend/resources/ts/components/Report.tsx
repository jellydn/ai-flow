import { Check, CheckCircle2, CircleDot, Copy, GitFork, Sparkles } from "lucide-react";
import type { RunResult } from "../types/api.ts";
import { shareRunUrl } from "../services/run.ts";
import { MarkdownBody } from "./MarkdownBody.tsx";

interface ReportProps {
    launcherName: string;
    repo: string;
    copied: boolean;
    setCopied: (copied: boolean) => void;
    reset: () => void;
    runId: string | null;
    result: RunResult | null;
    providerLabel: string | null;
    model: string | null;
}

export function Report({
    launcherName,
    repo,
    copied,
    setCopied,
    reset,
    runId,
    result,
    providerLabel,
    model,
}: ReportProps) {
    if (!result) {
        return (
            <main className="running-page">
                <div className="error-fallback">
                    <h1>Report unavailable</h1>
                    <p>This run has no structured result to display.</p>
                    <button type="button" onClick={reset}>
                        ← New launch
                    </button>
                </div>
            </main>
        );
    }

    const findings = result.findings ?? [];
    const summary = result.summary ?? "";
    const risk = result.risk ?? "medium";
    const checklist = result.verification_steps ?? [];
    const aiAttribution =
        providerLabel && model
            ? `${providerLabel} · ${model}`
            : providerLabel
              ? providerLabel
              : model
                ? model
                : null;

    const copy = async () => {
        if (!runId) {
            return;
        }
        await navigator.clipboard?.writeText(shareRunUrl(runId));
        setCopied(true);
        setTimeout(() => setCopied(false), 1800);
    };

    return (
        <main className="report-page">
            <div className="report-topline">
                <button type="button" className="back-button" onClick={reset}>
                    ← New launch
                </button>
                <div className="share-actions">
                    {runId ? (
                        <button type="button" onClick={copy}>
                            {copied ? <Check size={16} /> : <Copy size={16} />}
                            {copied ? "Copied" : "Copy link"}
                        </button>
                    ) : null}
                </div>
            </div>

            <section className="report-hero">
                <div className="report-status">
                    <CheckCircle2 size={16} /> Analysis complete
                </div>
                <h1>{launcherName}</h1>
                <div className="repo-name">
                    <GitFork size={18} /> {repo || "repository"}
                </div>
                {aiAttribution ? (
                    <p className="report-ai-provider">
                        Generated with <strong>{aiAttribution}</strong>
                    </p>
                ) : null}
                <div className="report-stats">
                    <div>
                        <span>Risk level</span>
                        <strong className="risk">
                            <CircleDot size={15} /> {risk}
                        </strong>
                    </div>
                    <div>
                        <span>Findings</span>
                        <strong>{findings.length}</strong>
                    </div>
                </div>
            </section>

            <div className="report-layout">
                <aside>
                    <span>On this page</span>
                    <a href="#summary" className="active">
                        Executive summary
                    </a>
                    <a href="#findings">
                        Key findings <b>{findings.length}</b>
                    </a>
                    <a href="#checklist">Verification checklist</a>
                    <div className="ai-card">
                        <Sparkles size={18} />
                        <strong>AI-generated report</strong>
                        {aiAttribution ? <p className="ai-card-provider">{aiAttribution}</p> : null}
                        <p>Always verify critical findings before merging.</p>
                    </div>
                </aside>
                <article className="report-content">
                    <section id="summary">
                        <div className="content-heading">
                            <span>01</span>
                            <h2>Executive summary</h2>
                        </div>
                        <div className="summary-box">
                            <MarkdownBody>{summary}</MarkdownBody>
                        </div>
                    </section>
                    <section id="findings">
                        <div className="content-heading">
                            <span>02</span>
                            <h2>Key findings</h2>
                            <b>{findings.length} findings</b>
                        </div>
                        <div className="findings-list">
                            {findings.map((finding, index) => (
                                <div
                                    className="finding"
                                    data-testid="finding"
                                    key={`${finding.title}::${finding.description}`}
                                >
                                    <div className="finding-header">
                                        <span
                                            className={`severity ${finding.severity}`}
                                            data-testid="finding-severity"
                                        >
                                            {finding.severity}
                                        </span>
                                        <span className="finding-number">
                                            {String(index + 1).padStart(2, "0")}
                                        </span>
                                    </div>
                                    <h3 data-testid="finding-title">{finding.title}</h3>
                                    <MarkdownBody>{finding.description}</MarkdownBody>
                                    <div className="suggestion">
                                        <strong>
                                            <Sparkles size={14} /> Suggested fix
                                        </strong>
                                        <MarkdownBody>{finding.recommendation}</MarkdownBody>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                    <section id="checklist">
                        <div className="content-heading">
                            <span>03</span>
                            <h2>Verification checklist</h2>
                        </div>
                        <div className="checklist">
                            {checklist.map((item) => (
                                <label key={item}>
                                    <input type="checkbox" />
                                    <span className="checklist-text">
                                        <MarkdownBody>{item}</MarkdownBody>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </section>
                </article>
            </div>
        </main>
    );
}
