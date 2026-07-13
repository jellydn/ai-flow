import { consola } from "consola";
import * as Sentry from "@sentry/react";

// Consola numeric levels: 0=fatal, 1=error, 2=warn, 3=info, 4=debug, 5=verbose.
// Dev: show everything (5). Prod: info and above (3).
const LOG_LEVEL_DEV = 5;
const LOG_LEVEL_PROD = 3;

const instance = consola.create({
    level: import.meta.env.PROD ? LOG_LEVEL_PROD : LOG_LEVEL_DEV,
});

/**
 * Unified function signature for logger methods.
 * Consola's typed methods use complex intersections that TS2556
 * doesn't recognise as rest parameters, so we cast through this.
 */
type LogFn = (...args: unknown[]) => void;

const rawError = instance.error as LogFn;
const rawWarn = instance.warn as LogFn;
const rawInfo = instance.info as LogFn;
const rawDebug = instance.debug as LogFn;
const rawVerbose = instance.verbose as LogFn;
const rawSuccess = instance.success as LogFn;

/**
 * Forward error-level arguments to Sentry.
 * Extracts the first `Error` instance for captureException;
 * falls back to captureMessage for non-Error args.
 */
function captureToSentry(args: unknown[]): void {
    const errorObj = args.find((a): a is Error => a instanceof Error);
    if (errorObj) {
        Sentry.captureException(errorObj, {
            extra: {
                logArgs: args.map((a) =>
                    a instanceof Error ? { name: a.name, message: a.message } : a,
                ),
            },
        });
    } else {
        Sentry.captureMessage(args.map((a) => String(a)).join(" "), "error");
    }
}

/**
 * Shared browser logger built on consola.
 *
 * - In development: verbose level (debug+) with fancy formatting.
 * - In production: info level — warnings and errors only.
 * - Error-level logs are forwarded to Sentry so they appear in the
 *   error dashboard alongside exceptions caught by ErrorBoundary.
 *
 * Usage:
 *   import { logger } from "../lib/logger.ts";
 *   logger.error("Logout request failed:", error);
 *   logger.warn("Unexpected response shape:", body);
 *   logger.info("Run started:", runId);
 */
export const logger = {
    error(...args: unknown[]): void {
        captureToSentry(args);
        rawError(...args);
    },
    warn: (...args: unknown[]) => rawWarn(...args),
    info: (...args: unknown[]) => rawInfo(...args),
    debug: (...args: unknown[]) => rawDebug(...args),
    verbose: (...args: unknown[]) => rawVerbose(...args),
    success: (...args: unknown[]) => rawSuccess(...args),
};
