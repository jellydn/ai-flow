import type { Workflow } from '../data/workflows.ts';

interface WorkflowIconProps {
    workflow: Workflow;
    size?: number;
}

export function WorkflowIcon({ workflow, size = 20 }: WorkflowIconProps) {
    const Icon = workflow.icon;
    return (
        <div className={`workflow-icon ${workflow.tone}`}>
            <Icon size={size} strokeWidth={2} />
        </div>
    );
}
