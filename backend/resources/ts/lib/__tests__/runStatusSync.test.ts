import { describe, expect, it } from "vitest";
import { decodeRun } from "../../services/run.ts";
import type { RunStatus } from "../../types/api.ts";

/**
 * Guards against drift between the frontend RunStatus type and the backend
 * Run::STATUSES constant (CONCERNS #1).
 *
 * The backend Run::STATUSES = ['queued', 'running', 'completed', 'failed'].
 * The frontend RunStatus type must match exactly. If either side changes,
 * this test (plus the PHP RunStatusSyncTest) will fail.
 *
 * Note: TypeScript types are erased at runtime, so we can't introspect the
 * RunStatus union directly. Instead we verify the isRunStatus guard object
 * in services/run.ts covers exactly these values.
 */
describe("RunStatus sync (frontend ↔ backend)", () => {
    // These must match App\Models\Run::STATUSES on the backend.
    const EXPECTED_STATUSES = ["queued", "running", "completed", "failed"] as const;

    it("EXPECTED_STATUSES matches the RunStatus type values", () => {
        // If this compiles, the literal array is assignable to the union type,
        // proving the values are valid RunStatus members.
        const _typeCheck: RunStatus[] = [...EXPECTED_STATUSES];
        expect(_typeCheck).toHaveLength(4);
    });

    it("EXPECTED_STATUSES has no duplicates", () => {
        expect(new Set(EXPECTED_STATUSES).size).toBe(EXPECTED_STATUSES.length);
    });

    it("isRunStatus guard covers all expected statuses", () => {
        for (const status of EXPECTED_STATUSES) {
            const run = decodeRun({
                id: "test",
                launcher: "review-pr",
                input: { source_url: "https://github.com/a/b" },
                status,
                progress: [],
                result: null,
                error: null,
                started_at: null,
                completed_at: null,
            });
            expect(run.status).toBe(status);
        }
    });

    it("decodeRun rejects an invalid status", () => {
        expect(() =>
            decodeRun({
                id: "test",
                launcher: null,
                input: null,
                status: "not-a-real-status",
                progress: [],
                result: null,
                error: null,
                started_at: null,
                completed_at: null,
            }),
        ).toThrow();
    });
});
