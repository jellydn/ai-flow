import { get, post } from "../lib/http.ts";
import { assertString, assertObject, assertArray } from "./run.ts";

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
        id: Number(assertString(data.id, "id")),
        name: data.name && typeof data.name === "string" ? data.name : null,
        email: assertString(data.email, "email"),
        email_verified_at: data.email_verified_at && typeof data.email_verified_at === "string" ? data.email_verified_at : null,
        last_login_at: data.last_login_at && typeof data.last_login_at === "string" ? data.last_login_at : null,
    };
}

export function decodeCredential(value: unknown): ProviderCredential {
    const data = assertObject(value);
    return {
        id: assertString(data.id, "id"),
        provider: assertString(data.provider, "provider"),
        label: assertString(data.label, "label"),
        masked_key: assertString(data.masked_key, "masked_key"),
        default_model: data.default_model && typeof data.default_model === "string" ? data.default_model : null,
        is_default: Boolean(data.is_default),
        last_verified_at: data.last_verified_at && typeof data.last_verified_at === "string" ? data.last_verified_at : null,
        last_used_at: data.last_used_at && typeof data.last_used_at === "string" ? data.last_used_at : null,
        created_at: assertString(data.created_at, "created_at"),
        updated_at: assertString(data.updated_at, "updated_at"),
    };
}

export async function requestMagicLink(email: string): Promise<void> {
    await post("/auth/magic-link", { email });
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

export async function fetchProviders(): Promise<{ id: string; name: string; models: string[] }[]> {
    const body = await get("/api/providers");
    return assertArray(body, "providers") as { id: string; name: string; models: string[] }[];
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
    return body as { valid: boolean; message: string };
}

export async function deleteCredential(id: string): Promise<void> {
    const raw = await fetch(`/api/user/provider-credentials/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json" },
    });
    if (!raw.ok) throw new Error("Failed to delete credential.");
}

export async function fetchUserRuns(params?: Record<string, string>): Promise<unknown> {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return get(`/api/user/runs${qs}`);
}

export async function retryRun(id: string): Promise<{ id: string; status: string }> {
    const body = await post(`/api/user/runs/${id}/retry`, {});
    return body as { id: string; status: string };
}

export async function deleteRun(id: string): Promise<void> {
    const raw = await fetch(`/api/user/runs/${id}`, {
        method: "DELETE",
        headers: { Accept: "application/json" },
    });
    if (!raw.ok) throw new Error("Failed to delete run.");
}
