export type ExecutionStatus = 'queued' | 'running' | 'completed' | 'failed';

export interface Flow {
    id: string;
    slug: string;
    name: string;
    description: string;
    inputType: string;
}

export interface Finding {
    severity: string;
    title: string;
    description: string;
    recommendation: string;
}

export interface ExecutionResult {
    summary: string;
    risk?: string;
    findings?: Finding[];
    verificationSteps?: string[];
}

export interface Execution {
    id: string;
    flowId: string | null;
    input: { source_url?: string } | null;
    status: ExecutionStatus;
    progress: string[];
    result: ExecutionResult | null;
    error: string | null;
    startedAt: string | null;
    completedAt: string | null;
}

export interface CreateExecutionResponse {
    id: string;
    status: string;
    message: string;
}
