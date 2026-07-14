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

export function isRunDetailPath(pathname: string): boolean {
    return /^\/?runs\/[0-9a-f-]+\/?$/i.test(pathname);
}
