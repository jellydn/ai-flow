import { Check, Loader2 } from "lucide-react";
import type { ProgressStep } from "../types/api.ts";

interface RunningProps {
    title: string;
    repo: string;
    steps: ProgressStep[];
    currentStep: number;
}

export function Running({ title, repo, steps, currentStep }: RunningProps) {
    const total = Math.max(steps.length, 5);
    const progress = Math.min(((currentStep + 1) / total) * 100, 100);

    return (
        <main className="running-page">
            <div className="run-heading">
                <div className="running-pulse">
                    <Loader2 size={22} className="spin" />
                </div>
                <div>
                    <h1>{title}</h1>
                    <p className="repo-name">{repo}</p>
                </div>
            </div>

            <div className="progress-card">
                <div className="progress-head">
                    <span>Working on your workflow…</span>
                    <strong>{Math.round(progress)}%</strong>
                </div>
                <div className="progress-track">
                    <span style={{ width: `${progress}%` }} />
                </div>

                {steps.map((step, index) => {
                    const complete = index < currentStep;
                    const current = index === currentStep;

                    let statusIcon: React.ReactNode;
                    if (complete) {
                        statusIcon = <Check size={15} />;
                    } else if (current) {
                        statusIcon = <Loader2 size={15} className="spin" />;
                    } else {
                        statusIcon = <span />;
                    }

                    const rowClass = [
                        "timeline-row",
                        complete ? "complete" : "",
                        current ? "current" : "",
                    ]
                        .filter(Boolean)
                        .join(" ");

                    return (
                        <div key={`${step.title}::${step.detail ?? ""}`} className={rowClass}>
                            <div className="status-icon">{statusIcon}</div>
                            <div>
                                <strong>{step.title}</strong>
                                {step.detail && <p>{step.detail}</p>}
                            </div>
                            {complete && <span className="done-label">Done</span>}
                        </div>
                    );
                })}
            </div>

            <p className="running-note">
                <Loader2 size={14} className="spin" /> This typically takes under a minute
            </p>
        </main>
    );
}
