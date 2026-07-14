/** Unique email per test run (avoids collisions on shared SQLite E2E DB). */
export function uniqueEmail(prefix = "e2e"): string {
    const stamp = Date.now().toString(36);
    const rand = Math.random().toString(36).slice(2, 8);
    return `${prefix}-${stamp}-${rand}@e2e.ai-flow.test`;
}

export const E2E_PASSWORD = "E2eTestPass1!";
