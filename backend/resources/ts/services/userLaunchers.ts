import { assertArray, assertObject, assertString } from "../lib/decode.ts";
import { get, mutationHeaders, post } from "../lib/http.ts";
import type { UserLauncher } from "../types/api.ts";

function decodeUserLauncher(value: unknown): UserLauncher {
    const data = assertObject(value);
    return {
        id: assertString(data.id, "id"),
        slug: assertString(data.slug, "slug"),
        name: assertString(data.name, "name"),
        description: assertString(data.description, "description"),
        prompt_template: assertString(data.prompt_template, "prompt_template"),
        input_type: assertString(data.input_type, "input_type"),
        output_schema: data.output_schema as Record<string, unknown>,
        is_custom: true,
        created_at: assertString(data.created_at, "created_at"),
        updated_at: assertString(data.updated_at, "updated_at"),
    };
}

export async function fetchUserLaunchers(): Promise<UserLauncher[]> {
    const body = await get("/api/user/launchers");
    const data = assertObject(body);
    return assertArray(data.data ?? body, "data").map(decodeUserLauncher);
}

export async function createUserLauncher(payload: {
    slug: string;
    name: string;
    description: string;
    prompt_template: string;
    input_type: string;
    output_schema: string;
}): Promise<UserLauncher> {
    const body = await post("/api/user/launchers", payload);
    const data = assertObject(body);
    return decodeUserLauncher(data.data ?? body);
}

export async function updateUserLauncher(
    id: string,
    payload: Partial<{
        name: string;
        description: string;
        prompt_template: string;
        input_type: string;
        output_schema: string;
    }>,
): Promise<UserLauncher> {
    const raw = await fetch(`/api/user/launchers/${id}`, {
        method: "PUT",
        headers: mutationHeaders({ "Content-Type": "application/json" }),
        credentials: "include",
        body: JSON.stringify(payload),
    });
    if (!raw.ok) {
        const err = await raw.json().catch(() => ({}));
        const msg =
            err && typeof err === "object" && "message" in err
                ? String((err as { message: string }).message)
                : "Failed to update launcher.";
        throw new Error(msg);
    }
    const body = await raw.json();
    const data = assertObject(body);
    return decodeUserLauncher(data.data ?? body);
}

export async function deleteUserLauncher(id: string): Promise<void> {
    const raw = await fetch(`/api/user/launchers/${id}`, {
        method: "DELETE",
        headers: mutationHeaders(),
        credentials: "include",
    });
    if (!raw.ok) throw new Error("Failed to delete custom launcher.");
}

export async function fetchHiddenLaunchers(): Promise<string[]> {
    const body = await get("/api/user/hidden-launchers");
    const data = assertObject(body);
    return assertArray(data.data ?? body, "data").map((item, index) =>
        assertString(item, `hidden[${index}]`),
    );
}

export async function hideLauncher(slug: string): Promise<void> {
    const raw = await fetch(`/api/user/hidden-launchers/${slug}`, {
        method: "POST",
        headers: mutationHeaders(),
        credentials: "include",
    });
    if (!raw.ok) throw new Error("Failed to hide launcher.");
}

export async function unhideLauncher(slug: string): Promise<void> {
    const raw = await fetch(`/api/user/hidden-launchers/${slug}`, {
        method: "DELETE",
        headers: mutationHeaders(),
        credentials: "include",
    });
    if (!raw.ok) throw new Error("Failed to unhide launcher.");
}
