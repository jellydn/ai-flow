const DEFAULT_TIMEOUT = 10_000;

function getCookie(name: string): string | undefined {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
    return match ? match[1] : undefined;
}

export function mutationHeaders(extra: Record<string, string> = {}): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: "application/json",
        ...extra,
    };
    const xsrfToken = getCookie("XSRF-TOKEN");
    if (xsrfToken) {
        headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrfToken);
    }
    return headers;
}

function buildErrorMessage(response: Response, body: unknown): string {
    if (
        body &&
        typeof body === "object" &&
        "message" in body &&
        typeof (body as Record<string, unknown>).message === "string"
    ) {
        return (body as Record<string, unknown>).message as string;
    }
    if (
        body &&
        typeof body === "object" &&
        "error" in body &&
        typeof (body as Record<string, unknown>).error === "string"
    ) {
        return (body as Record<string, unknown>).error as string;
    }
    return `HTTP ${response.status} ${response.statusText}`;
}

async function parseJson(response: Response): Promise<unknown> {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch {
        throw new Error(`Invalid JSON response from ${response.url} (status ${response.status}).`);
    }
}

async function request(input: RequestInfo, init: RequestInit, timeout: number): Promise<unknown> {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeout);
    const method = (init.method ?? "GET").toUpperCase();
    const headers =
        method === "GET" || method === "HEAD"
            ? { Accept: "application/json", ...(init.headers as Record<string, string>) }
            : mutationHeaders(init.headers as Record<string, string>);
    try {
        const response = await fetch(input, {
            ...init,
            headers,
            credentials: "include",
            signal: controller.signal,
        });
        clearTimeout(id);
        const body = await parseJson(response);
        if (!response.ok) {
            throw new Error(buildErrorMessage(response, body));
        }
        return body;
    } catch (error) {
        clearTimeout(id);
        if (error instanceof Error && error.name === "AbortError") {
            throw new Error("Request timed out. Is the API reachable?");
        }
        throw error;
    }
}

export function get(path: string, timeout: number = DEFAULT_TIMEOUT): Promise<unknown> {
    return request(path, { headers: { Accept: "application/json" } }, timeout);
}

export function post(
    path: string,
    payload: unknown,
    timeout: number = DEFAULT_TIMEOUT,
): Promise<unknown> {
    return request(
        path,
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        },
        timeout,
    );
}
