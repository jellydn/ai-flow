/**
 * Runtime type-assertion helpers for decoding untrusted API JSON.
 *
 * Shared by services/run.ts and services/auth.ts so neither service has
 * to import from the other for utility access (CONCERNS T5).
 *
 * Each helper throws a descriptive Error when the value does not match
 * the expected shape, narrowing `unknown` to a concrete type on success.
 */

export function assertObject(value: unknown): Record<string, unknown> {
    if (value === null || typeof value !== "object") {
        throw new Error("Expected an object.");
    }
    return value as Record<string, unknown>;
}

export function assertString(value: unknown, field: string): string {
    if (typeof value !== "string") {
        throw new Error(`Expected ${field} to be a string.`);
    }
    return value;
}

/** Laravel JSON often encodes integer primary keys as numbers, not strings. */
export function assertIntegerId(value: unknown, field: string): number {
    if (typeof value === "number" && Number.isInteger(value)) {
        return value;
    }
    if (typeof value === "string" && value !== "" && Number.isInteger(Number(value))) {
        return Number(value);
    }
    throw new Error(`Expected ${field} to be an integer id.`);
}

export function assertStringOrNull(value: unknown, field: string): string | null {
    if (value === null || value === undefined) {
        return null;
    }
    if (typeof value !== "string") {
        throw new Error(`Expected ${field} to be a string or null.`);
    }
    return value;
}

export function assertArray(value: unknown, field: string): unknown[] {
    if (!Array.isArray(value)) {
        throw new Error(`Expected ${field} to be an array.`);
    }
    return value;
}
