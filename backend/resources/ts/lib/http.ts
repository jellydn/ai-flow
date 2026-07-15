const DEFAULT_TIMEOUT = 10_000;

function getCookie(name: string): string | undefined {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
    return match ? match[1] : undefined;
}

function getCsrfTokenFromMeta(): string | undefined {
    const content = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
    return content && content.length > 0 ? content : undefined;
}

export function mutationHeaders(extra: Record<string, string> = {}): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: "application/json",
        ...extra,
    };
    const cookieToken = getCookie("XSRF-TOKEN");
    if (cookieToken) {
        try {
            headers["X-XSRF-TOKEN"] = decodeURIComponent(cookieToken);
        } catch {
            headers["X-XSRF-TOKEN"] = cookieToken;
        }
    } else {
        const metaToken = getCsrfTokenFromMeta();
        if (metaToken) {
            headers["X-CSRF-TOKEN"] = metaToken;
        }
    }
    return headers;
}

function firstValidationErrorFromBag(body: Record<string, unknown>): string | undefined {
    const errors = body.errors;
    if (!errors || typeof errors !== "object") {
        return undefined;
    }
    for (const messages of Object.values(errors as Record<string, unknown>)) {
        if (Array.isArray(messages) && typeof messages[0] === "string") {
            return messages[0];
        }
    }
    return undefined;
}

function buildErrorMessage(response: Response, body: unknown): string {
    if (body && typeof body === "object") {
        const record = body as Record<string, unknown>;
        const fromBag = firstValidationErrorFromBag(record);
        if (fromBag) {
            return fromBag;
        }
    }
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
