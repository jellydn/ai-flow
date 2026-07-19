export type RunStatus = "queued" | "running" | "completed" | "failed";

export interface Run {
    id: string;
    launcher: string | null;
    input: { source_url?: string } | null;
    status: RunStatus;
    progress: string[];
    result: RunResult | null;
    error: string | null;
    provider?: string | null;
    provider_label?: string | null;
    model?: string | null;
    is_public?: boolean;
    started_at: string | null;
    completed_at: string | null;
    created_at?: string;
}

export interface RunResult {
    summary?: string;
    risk?: string;
    findings?: Finding[];
    verification_steps?: string[];
}

export interface Finding {
    severity: string;
    title: string;
    description: string;
    recommendation: string;
}

export interface Launcher {
    id: string;
    slug: string;
    name: string;
    description: string;
    input_type: string;
    icon?: string;
    tone?: string;
    is_custom?: boolean;
}

export interface UserLauncher {
    id: string;
    slug: string;
    name: string;
    description: string;
    prompt_template: string;
    input_type: string;
    output_schema: Record<string, unknown>;
    is_custom: true;
    created_at: string;
    updated_at: string;
}

export interface CreateRunResponse {
    id: string;
    status: RunStatus;
    message: string;
}

export interface ProgressStep {
    title: string;
    detail?: string;
}
