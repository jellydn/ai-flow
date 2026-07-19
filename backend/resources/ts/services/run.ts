import { assertArray, assertObject, assertString, assertStringOrNull } from "../lib/decode.ts";
import { get, post } from "../lib/http.ts";
import type {
    CreateRunResponse,
    Finding,
    Launcher,
    Run,
    RunResult,
    RunStatus,
} from "../types/api.ts";

// Keep in sync with the backend status enum App\Models\Run::STATUSES / TERMINAL_STATUSES.
const isRunStatus: { [K in RunStatus]: true } = {
    queued: true,
    running: true,
    completed: true,
    failed: true,
};

function decodeFinding(value: unknown): Finding {
    const data = assertObject(value);
    return {
        severity: assertString(data.severity, "finding.severity"),
        title: assertString(data.title, "finding.title"),
        description: assertString(data.description, "finding.description"),
        recommendation: assertString(data.recommendation, "finding.recommendation"),
    };
}

function decodeFindings(value: unknown): Finding[] {
    return assertArray(value, "result.findings").map(decodeFinding);
}

function decodeVerificationSteps(value: unknown): string[] {
    return assertArray(value, "result.verification_steps").map((item, index) =>
        assertString(item, `verification_steps[${index}]`),
    );
}

function decodeRunInput(value: unknown): Run["input"] {
    if (value === null || value === undefined) {
        return null;
    }
    const data = assertObject(value);
    if (data.source_url === undefined) {
        return {};
    }
    return { source_url: assertString(data.source_url, "input.source_url") };
}

function decodeRunResult(value: unknown): RunResult {
    const data = assertObject(value);
    const result: RunResult = {};

    // summary is optional — custom launchers may define arbitrary output schemas
    if (typeof data.summary === "string") {
        result.summary = data.summary;
    }

    if (data.risk !== undefined) {
        result.risk = assertString(data.risk, "result.risk");
    }

    if (data.findings !== undefined) {
        result.findings = decodeFindings(data.findings);
    }

    if (data.verification_steps !== undefined) {
        result.verification_steps = decodeVerificationSteps(data.verification_steps);
    }

    return result;
}

export function decodeRun(value: unknown): Run {
    const data = assertObject(value);

    const result =
        data.result === null || data.result === undefined ? null : decodeRunResult(data.result);

    const input = decodeRunInput(data.input);

    const progress = assertArray(data.progress, "progress").map((item, index) =>
        assertString(item, `progress[${index}]`),
    );

    const status = data.status;
    if (typeof status !== "string" || !(status in isRunStatus)) {
        throw new Error(`Expected status to be a valid run status, got ${typeof status}.`);
    }

    return {
        id: assertString(data.id, "id"),
        launcher: assertStringOrNull(data.launcher, "launcher"),
        input,
        status: status as RunStatus,
        progress,
        result,
        error: assertStringOrNull(data.error, "error"),
        provider: assertStringOrNull(data.provider, "provider") ?? undefined,
        provider_label: assertStringOrNull(data.provider_label, "provider_label") ?? undefined,
        model: assertStringOrNull(data.model, "model") ?? undefined,
        is_public: typeof data.is_public === "boolean" ? data.is_public : undefined,
        started_at: assertStringOrNull(data.started_at, "started_at"),
        completed_at: assertStringOrNull(data.completed_at, "completed_at"),
        created_at: assertStringOrNull(data.created_at, "created_at") ?? undefined,
    };
}

function decodeLauncher(value: unknown): Launcher {
    const data = assertObject(value);
    return {
        id: assertString(data.id, "id"),
        slug: assertString(data.slug, "slug"),
        name: assertString(data.name, "name"),
        description: assertString(data.description, "description"),
        input_type: assertString(data.input_type, "input_type"),
        icon: typeof data.icon === "string" ? data.icon : undefined,
        tone: typeof data.tone === "string" ? data.tone : undefined,
        is_custom: typeof data.is_custom === "boolean" ? data.is_custom : undefined,
    };
}

export async function getLaunchers(opts?: { includeHidden?: boolean }): Promise<Launcher[]> {
    const path = opts?.includeHidden ? "/api/launchers?include_hidden=1" : "/api/launchers";
    const body = await get(path);
    const items = assertArray(body, "launchers");
    return items.map(decodeLauncher);
}

export interface RecentRunSummary {
    id: string;
    repo: string | null;
    type: string;
    launcher_slug: string | null;
    launcher_name: string | null;
    risk: string;
    findings_count: number;
    has_verification_steps: boolean;
    duration_seconds: number | null;
    completed_at: string | null;
}

function decodeRecentRun(value: unknown): RecentRunSummary {
    const data = assertObject(value);
    return {
        id: assertString(data.id, "id"),
        repo: data.repo && typeof data.repo === "string" ? data.repo : null,
        type: assertString(data.type, "type"),
        launcher_slug:
            data.launcher_slug && typeof data.launcher_slug === "string"
                ? data.launcher_slug
                : null,
        launcher_name:
            data.launcher_name && typeof data.launcher_name === "string"
                ? data.launcher_name
                : null,
        risk: assertString(data.risk, "risk"),
        findings_count: typeof data.findings_count === "number" ? data.findings_count : 0,
        has_verification_steps: Boolean(data.has_verification_steps),
        duration_seconds:
            data.duration_seconds !== null && data.duration_seconds !== undefined
                ? Number(data.duration_seconds)
                : null,
        completed_at:
            data.completed_at && typeof data.completed_at === "string" ? data.completed_at : null,
    };
}

export async function fetchRecentRuns(): Promise<RecentRunSummary[]> {
    const body = await get("/api/runs/recent");
    const payload = assertObject(body);
    const items = assertArray(payload.data ?? body, "data");
    return items.map(decodeRecentRun);
}

export interface TrendingRepositorySummary {
    repo: string;
    url: string;
}

function decodeTrendingRepository(value: unknown): TrendingRepositorySummary {
    const data = assertObject(value);
    return {
        repo: assertString(data.repo, "repo"),
        url: assertString(data.url, "url"),
    };
}

export async function fetchTrendingRepositories(): Promise<TrendingRepositorySummary[]> {
    const body = await get("/api/trending-repositories");
    const payload = assertObject(body);
    const items = assertArray(payload.data ?? body, "data");
    return items.map(decodeTrendingRepository);
}

export async function fetchRun(id: string): Promise<Run> {
    const body = await get(`/api/runs/${encodeURIComponent(id)}`);
    const payload =
        body &&
        typeof body === "object" &&
        "data" in (body as Record<string, unknown>) &&
        typeof (body as Record<string, unknown>).data === "object"
            ? (body as Record<string, unknown>).data
            : body;
    return decodeRun(payload);
}

export type RunProviderId = "openai" | "openrouter" | "anthropic" | "gemini";

export async function createRun(
    launcher: string,
    sourceUrl: string,
    providerId: RunProviderId,
    apiKey: string,
    providerCredentialId?: string,
    model?: string,
    isPublic?: boolean,
): Promise<CreateRunResponse> {
    const trimmedKey = apiKey.trim();
    const trimmedModel = model?.trim();
    const payload: Record<string, unknown> = {
        launcher,
        source_url: sourceUrl,
        provider: {
            id: providerId,
            ...(trimmedKey !== "" ? { api_key: trimmedKey } : {}),
            ...(trimmedModel ? { model: trimmedModel } : {}),
        },
        ...(providerCredentialId ? { provider_credential_id: providerCredentialId } : {}),
    };
    if (isPublic !== undefined) {
        payload.is_public = isPublic;
    }
    const body = await post("/api/runs", payload);
    const data = assertObject(body);
    return {
        id: assertString(data.id, "id"),
        status: assertString(data.status, "status") as RunStatus,
        message: assertString(data.message, "message"),
    };
}

export function shareRunUrl(id: string): string {
    return `${window.location.origin}/runs/${id}`;
}

const GITHUB_HOSTS = ["github.com", "www.github.com"];

export function parseGithubRepo(url: string): string | null {
    try {
        const parsed = new URL(url.trim());
        if (parsed.protocol !== "https:" || !GITHUB_HOSTS.includes(parsed.hostname)) {
            return null;
        }
        const parts = parsed.pathname.split("/").filter(Boolean);
        if (parts.length < 2) {
            return null;
        }
        return `${parts[0]}/${parts[1].replace(/\.git$/i, "")}`;
    } catch {
        return null;
    }
}

export function isValidGithubUrl(url: string): boolean {
    return parseGithubRepo(url) !== null;
}
