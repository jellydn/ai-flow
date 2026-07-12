import { Check, Loader2 } from 'lucide-react';
import type { ProgressStep } from '../types/api.ts';

interface RunningProps {
    title: string;
    repo: string;
    steps: ProgressStep[];
    currentStep: number;
}

export function Running({ title, repo, steps, currentStep }: RunningProps) {
    const total = Math.max(steps.length, 5);
    const progress = Math.min((currentStep / total) * 100, 100);

    return (
        <main className="running-page">
            <div className="running-header">
                <h1>{title}</h1>
                <p className="repo-name">{repo}</p>
                <div className="progress-bar">
                    <div className="progress-fill" style={{ width: `${progress}%` }} />
                </div>
            </div>

            <div className="running-steps">
                {steps.map((step, index) => {
                    const complete = index < currentStep;
                    const current = index === currentStep;
                    const pending = index > currentStep;
                    let subtitle: string;
                    if (complete) {
                        subtitle = step.detail || 'Done';
                    } else if (current) {
                        subtitle = step.detail || 'In progress…';
                    } else {
                        subtitle = 'Waiting…';
                    }

                    return (
                        <div
                            key={`${step.title}-${index}`}
                            className={`step ${complete ? 'done' : ''} ${current ? 'current' : ''} ${pending ? 'pending' : ''}`}
                        >
                            <div className="step-icon">
                                {complete ? <Check size={18} /> : current ? <Loader2 size={18} className="spin" /> : <span />}
                            </div>
                            <div className="step-body">
                                <div className="step-title">{step.title}</div>
                                <div className="step-subtitle">{subtitle}</div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </main>
    );
}
