import { assertArray, assertIntegerId, assertObject, assertString } from "../lib/decode.ts";
import { get, mutationHeaders, post } from "../lib/http.ts";

export interface User {
    id: number;
    name: string | null;
    email: string;
    email_verified_at: string | null;
    last_login_at: string | null;
}

export interface ProviderCredential {
    id: string;
    provider: string;
    label: string;
    masked_key: string;
    default_model: string | null;
    is_default: boolean;
    last_verified_at: string | null;
    last_used_at: string | null;
    created_at: string;
    updated_at: string;
}

export function decodeUser(value: unknown): User {
    const data = assertObject(value);
    return {
        id: assertIntegerId(data.id, "id"),
        name: data.name && typeof data.name === "string" ? data.name : null,
        email: assertString(data.email, "email"),
        email_verified_at:
            data.email_verified_at && typeof data.email_verified_at === "string"
                ? data.email_verified_at
                : null,
        last_login_at:
            data.last_login_at && typeof data.last_login_at === "string"
                ? data.last_login_at
                : null,
    };
}

export function decodeCredential(value: unknown): ProviderCredential {
    const data = assertObject(value);
    return {
        id: assertString(data.id, "id"),
        provider: assertString(data.provider, "provider"),
        label: assertString(data.label, "label"),
        masked_key: assertString(data.masked_key, "masked_key"),
        default_model:
            data.default_model && typeof data.default_model === "string"
                ? data.default_model
                : null,
        is_default: Boolean(data.is_default),
        last_verified_at:
            data.last_verified_at && typeof data.last_verified_at === "string"
                ? data.last_verified_at
                : null,
        last_used_at:
            data.last_used_at && typeof data.last_used_at === "string" ? data.last_used_at : null,
        created_at: assertString(data.created_at, "created_at"),
        updated_at: assertString(data.updated_at, "updated_at"),
    };
}

export async function requestMagicLink(email: string): Promise<void> {
    await post("/auth/magic-link", { email });
}

export async function loginWithPassword(email: string, password: string): Promise<User> {
    const body = await post("/auth/login", { email, password });
    const data = assertObject(body);
    return decodeUser(data.data ?? body);
}

export async function registerWithPassword(payload: {
    email: string;
    password: string;
    password_confirmation: string;
    name?: string;
}): Promise<User> {
    const body = await post("/auth/register", payload);
    const data = assertObject(body);
    return decodeUser(data.data ?? body);
}

export async function logout(): Promise<void> {
    await post("/auth/logout", {});
}

export async function fetchUser(): Promise<User> {
    const body = await get("/api/user");
    const data = assertObject(body);
    const user = data.data ? data.data : body;
    return decodeUser(user);
}

export interface ProviderSummary {
    id: string;
    name: string;
    models: string[];
}

export function decodeProvider(value: unknown): ProviderSummary {
    const data = assertObject(value);
    return {
        id: assertString(data.id, "provider.id"),
        name: assertString(data.name, "provider.name"),
        models: assertArray(data.models, "provider.models").map((item, index) =>
            assertString(item, `provider.models[${index}]`),
        ),
    };
}

export async function fetchProviders(): Promise<ProviderSummary[]> {
    const body = await get("/api/providers");
    const payload = assertObject(body);
    return assertArray(payload.data ?? body, "providers").map(decodeProvider);
}

export async function fetchCredentials(): Promise<ProviderCredential[]> {
    const body = await get("/api/user/provider-credentials");
    const data = assertObject(body);
    return assertArray(data.data ?? body, "data").map(decodeCredential);
}

export async function createCredential(payload: {
    provider: string;
    label: string;
    api_key: string;
    default_model?: string;
    is_default?: boolean;
}): Promise<ProviderCredential> {
    const body = await post("/api/user/provider-credentials", payload);
    const data = assertObject(body);
    return decodeCredential(data.data ?? body);
}

export async function verifyCredential(id: string): Promise<{ valid: boolean; message: string }> {
    const body = await post(`/api/user/provider-credentials/${id}/verify`, {});
    const data = assertObject(body);
    return {
        valid: Boolean(data.valid),
        message: assertString(data.message, "message"),
    };
}

export async function deleteCredential(id: string): Promise<void> {
    const raw = await fetch(`/api/user/provider-credentials/${id}`, {
        method: "DELETE",
        headers: mutationHeaders(),
        credentials: "include",
    });
    if (!raw.ok) throw new Error("Failed to delete credential.");
}

export async function fetchUserRuns(params?: Record<string, string>): Promise<unknown> {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return get(`/api/user/runs${qs}`);
}

export async function retryRun(id: string): Promise<{ id: string; status: string }> {
    const body = await post(`/api/user/runs/${id}/retry`, {});
    const data = assertObject(body);
    return {
        id: assertString(data.id, "id"),
        status: assertString(data.status, "status"),
    };
}

export async function deleteRun(id: string): Promise<void> {
    const raw = await fetch(`/api/user/runs/${id}`, {
        method: "DELETE",
        headers: mutationHeaders(),
        credentials: "include",
    });
    if (!raw.ok) throw new Error("Failed to delete run.");
}

export interface LauncherPromptEntry {
    slug: string;
    name: string;
    default_prompt_template: string;
    override_prompt_template: string | null;
    uses_override: boolean;
}

export function decodeLauncherPromptEntry(value: unknown): LauncherPromptEntry {
    const data = assertObject(value);
    return {
        slug: assertString(data.slug, "slug"),
        name: assertString(data.name, "name"),
        default_prompt_template: assertString(
            data.default_prompt_template,
            "default_prompt_template",
        ),
        override_prompt_template:
            typeof data.override_prompt_template === "string"
                ? data.override_prompt_template
                : null,
        uses_override: Boolean(data.uses_override),
    };
}

export async function fetchLauncherPrompts(): Promise<LauncherPromptEntry[]> {
    const body = await get("/api/user/launcher-prompts");
    const data = assertObject(body);
    return assertArray(data.data ?? body, "data").map(decodeLauncherPromptEntry);
}

export async function upsertLauncherPrompt(slug: string, prompt_template: string): Promise<void> {
    const raw = await fetch(`/api/user/launcher-prompts/${slug}`, {
        method: "PUT",
        headers: mutationHeaders({ "Content-Type": "application/json" }),
        credentials: "include",
        body: JSON.stringify({ prompt_template }),
    });
    if (!raw.ok) {
        const err = await raw.json().catch(() => ({}));
        const msg =
            err && typeof err === "object" && "message" in err
                ? String((err as { message: string }).message)
                : "Failed to save workflow prompt.";
        throw new Error(msg);
    }
}

export async function deleteLauncherPrompt(slug: string): Promise<void> {
    const raw = await fetch(`/api/user/launcher-prompts/${slug}`, {
        method: "DELETE",
        headers: mutationHeaders(),
        credentials: "include",
    });
    if (!raw.ok) throw new Error("Failed to reset workflow prompt.");
}

export async function deleteAccount(): Promise<void> {
    const raw = await fetch(`/api/user/account`, {
        method: "DELETE",
        headers: mutationHeaders({ "Content-Type": "application/json" }),
        credentials: "include",
        body: JSON.stringify({ confirm: true }),
    });
    if (!raw.ok) throw new Error("Failed to delete account.");
}
