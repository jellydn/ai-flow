import type { APIRequestContext } from "@playwright/test";

/** Prime session + return X-XSRF-TOKEN header for Laravel web routes. */
export async function csrfHeaders(request: APIRequestContext): Promise<Record<string, string>> {
    await request.get("/");
    const cookies = await request.storageState();
    const xsrf = cookies.cookies.find((c) => c.name === "XSRF-TOKEN")?.value;
    const headers: Record<string, string> = {
        Accept: "application/json",
        "Content-Type": "application/json",
    };
    if (xsrf) {
        headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrf);
    }
    return headers;
}

export async function postAuthJson(
    request: APIRequestContext,
    path: string,
    data: Record<string, unknown>,
): Promise<import("@playwright/test").APIResponse> {
    const headers = await csrfHeaders(request);
    return request.post(path, { headers, data });
}

export async function putAuthJson(
    request: APIRequestContext,
    path: string,
    data: Record<string, unknown>,
): Promise<import("@playwright/test").APIResponse> {
    const headers = await csrfHeaders(request);
    return request.put(path, { headers, data });
}
