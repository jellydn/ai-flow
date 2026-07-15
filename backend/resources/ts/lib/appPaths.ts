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
