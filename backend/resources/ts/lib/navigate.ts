/**
 * Navigate to a path within the SPA, updating the browser history
 * so deep-links and the back button work correctly.
 */
export function goto(path: string, navigate: (pathname: string) => void): void {
    window.history.pushState({}, "", path);
    navigate(path);
}

/**
 * Replace the current history entry (no new back-button entry) and navigate.
 * Use for redirects where the previous URL shouldn't be reachable via Back —
 * e.g. unauthenticated /user → /login, or post-auth /login → /user.
 */
export function replaceGoto(path: string, navigate: (pathname: string) => void): void {
    window.history.replaceState({}, "", path);
    navigate(path);
}
