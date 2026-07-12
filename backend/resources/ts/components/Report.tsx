import { Check, CheckCircle2, CircleDot, Copy, GitFork, Sparkles } from 'lucide-react';
import type { Finding, RunResult } from '../types/api.ts';
import { demoFindings } from '../data/launcherMeta.ts';
import { shareRunUrl } from '../services/run.ts';

interface ReportProps {
    launcherName: string;
    repo: string;
    copied: boolean;
    setCopied: (copied: boolean) => void;
    reset: () => void;
    runId: string | null;
    result: RunResult | null;
}

export function Report({ launcherName, repo, copied, setCopied, reset, runId, result }: ReportProps) {
    const isDemo = !runId && !result;
    const findings: Finding[] = isDemo ? demoFindings : (result?.findings ?? []);
    const summary = isDemo
        ? 'This pull request introduces useful filtering and organization features, but contains one authorization vulnerability that should be fixed before merging.'
        : (result?.summary ?? '');
    const risk = isDemo ? 'medium' : (result?.risk ?? 'medium');
    const checklist = isDemo
        ? ['Add authorization policy check before deleting tools', 'Replace usage counter update with atomic increment', 'Add feature tests for combined filters', 'Run the full test suite before merge']
        : (result?.verification_steps ?? []);

    const copy = async () => {
        const link = runId ? shareRunUrl(runId) : `${window.location.origin}/runs/demo`;
        await navigator.clipboard?.writeText(link);
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
                    <button type="button" onClick={copy}>
                        {copied ? <Check size={16} /> : <Copy size={16} />}
                        {copied ? 'Copied' : 'Copy link'}
                    </button>
                </div>
            </div>

            <section className="report-hero">
                <div className="report-status">
                    <CheckCircle2 size={16} /> Analysis complete
                </div>
                <h1>{launcherName}</h1>
                <div className="repo-name">
                    <GitFork size={18} /> {repo || 'repository'}
                </div>
                <div className="report-stats">
                    <div>
                        <span>Risk level</span>
                        <strong className="risk"><CircleDot size={15} /> {risk}</strong>
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
                    <a href="#summary" className="active">Executive summary</a>
                    <a href="#findings">
                        Key findings <b>{findings.length}</b>
                    </a>
                    <a href="#checklist">Verification checklist</a>
                    <div className="ai-card">
                        <Sparkles size={18} />
                        <strong>AI-generated report</strong>
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
                            <p>{summary}</p>
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
                                <div className="finding" key={`${finding.title}-${index}`}>
                                    <div className="finding-header">
                                        <span className={`severity ${finding.severity}`}>{finding.severity}</span>
                                        <span className="finding-number">{String(index + 1).padStart(2, '0')}</span>
                                    </div>
                                    <h3>{finding.title}</h3>
                                    <p>{finding.description}</p>
                                    <div className="suggestion">
                                        <strong><Sparkles size={14} /> Suggested fix</strong>
                                        <p>{finding.recommendation}</p>
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
                            {checklist.map((item, index) => (
                                <label key={item}>
                                    <input type="checkbox" defaultChecked={index === checklist.length - 1} />
                                    <span>{item}</span>
                                </label>
                            ))}
                        </div>
                    </section>
                </article>
            </div>
        </main>
    );
}
