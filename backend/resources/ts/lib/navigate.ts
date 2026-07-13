/**
 * Navigate to a path within the SPA, updating the browser history
 * so deep-links and the back button work correctly.
 */
export function goto(path: string, navigate: (pathname: string) => void): void {
    window.history.pushState({}, "", path);
    navigate(path);
}
