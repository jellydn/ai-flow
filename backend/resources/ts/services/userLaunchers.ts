import { assertArray, assertObject, assertString } from "../lib/decode.ts";
import { del, get, post, put } from "../lib/http.ts";
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
    const body = await put(`/api/user/launchers/${id}`, payload);
    const data = assertObject(body);
    return decodeUserLauncher(data.data ?? body);
}

export async function deleteUserLauncher(id: string): Promise<void> {
    await del(`/api/user/launchers/${id}`);
}

export async function fetchHiddenLaunchers(): Promise<string[]> {
    const body = await get("/api/user/hidden-launchers");
    const data = assertObject(body);
    return assertArray(data.data ?? body, "data").map((item, index) =>
        assertString(item, `hidden[${index}]`),
    );
}

export async function hideLauncher(slug: string): Promise<void> {
    await post(`/api/user/hidden-launchers/${slug}`, {});
}

export async function unhideLauncher(slug: string): Promise<void> {
    await del(`/api/user/hidden-launchers/${slug}`);
}
