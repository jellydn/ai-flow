/** Normalized pathname without trailing slash (except `/`). */
export function normalizePathname(pathname: string): string {
    if (!pathname || pathname === "/") {
        return "/";
    }
    return pathname.replace(/\/+$/, "") || "/";
}

export function isUserAccountPath(pathname: string): boolean {
    return normalizePathname(pathname) === "/user";
}

const RUN_DETAIL_PATH = /^\/?runs\/([0-9a-f-]+)\/?$/i;

export function isRunDetailPath(pathname: string): boolean {
    return RUN_DETAIL_PATH.test(pathname);
}

/** Returns the run UUID from a detail path, or null if the path is not a run detail URL. */
export function getRunIdFromPath(pathname: string): string | null {
    const match = pathname.match(RUN_DETAIL_PATH);
    return match?.[1] ?? null;
}

/* ── Auth routes ────────────────────────────────────────────────── */

/** Pathnames for the dedicated auth routes (dedicated routes so refresh doesn't lose state). */
export const LOGIN_PATH = "/login";
export const SIGNUP_PATH = "/signup";
export const CHECK_EMAIL_PATH = "/check-email";

export function isSignInPath(pathname: string): boolean {
    const normalized = normalizePathname(pathname);
    return normalized === LOGIN_PATH || normalized === SIGNUP_PATH;
}

export function isCheckEmailPath(pathname: string): boolean {
    return normalizePathname(pathname) === CHECK_EMAIL_PATH;
}

/** True for any of the dedicated auth routes (login, signup, check-email). */
export function isAuthPath(pathname: string): boolean {
    return isSignInPath(pathname) || isCheckEmailPath(pathname);
}

/**
 * Returns the `email` query param from the current URL (used by /check-email
 * to persist the submitted address across refresh). URL-encoded by the caller.
 */
export function getEmailFromQuery(): string {
    return new URLSearchParams(window.location.search).get("email") ?? "";
}
