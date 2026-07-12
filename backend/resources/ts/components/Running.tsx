import { Bot, Check, LoaderCircle, ShieldCheck } from 'lucide-react';
import type { Workflow } from '../data/workflows.ts';

interface RunningProps {
    activeWorkflow: Workflow | undefined;
    repo: string;
    step: number;
    executionSteps: Array<[string, string]>;
    live: boolean;
}

export function Running({ activeWorkflow, repo, step, executionSteps, live }: RunningProps) {
    const total = Math.max(executionSteps.length, 5);
    const progress = Math.min(100, Math.max(0, Math.round((step / total) * 100)));

    return (
        <main className="running-page">
            <div className="run-heading">
                <div className="running-pulse"><Bot size={22} /></div>
                <div>
                    <div className="eyebrow">Workflow in progress</div>
                    <h1>
                        Analyzing <em>{repo}</em>
                    </h1>
                    <p>{activeWorkflow?.title} · This usually takes less than a minute</p>
                </div>
            </div>
            <div className="progress-card">
                <div className="progress-head">
                    <span>AI analysis</span>
                    <strong>{progress}%</strong>
                </div>
                <div className="progress-track">
                    <span style={{ width: `${progress}%` }} />
                </div>
                <div className="timeline">
                    {executionSteps.map(([title, detail], index) => {
                        const complete = index < step;
                        const current = index === step;
                        let subtitle = 'Waiting…';
                        if (complete) {
                            subtitle = detail || 'Done';
                        } else if (current) {
                            subtitle = detail || (live ? 'In progress…' : 'Working…');
                        }
                        return (
                            <div
                                className={`timeline-row ${complete ? 'complete' : ''} ${current ? 'current' : ''}`}
                                key={`${title}-${index}`}
                            >
                                <div className="status-icon">
                                    {complete ? <Check size={16} /> : current ? <LoaderCircle size={16} className="spin" /> : <span />}
                                </div>
                                <div>
                                    <strong>{title}</strong>
                                    <p>{subtitle}</p>
                                </div>
                                {complete && <span className="done-label">Done</span>}
                            </div>
                        );
                    })}
                </div>
            </div>
            <p className="running-note">
                <ShieldCheck size={16} /> GitHub context is used for analysis and cleared from storage after the report is ready.
            </p>
        </main>
    );
}
