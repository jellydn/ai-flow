import { useCallback, useEffect, useState } from "react";
import { EyeOff, Eye } from "lucide-react";
import { fetchHiddenLaunchers, hideLauncher, unhideLauncher } from "../services/userLaunchers.ts";
import { getLaunchers } from "../services/run.ts";
import type { Launcher } from "../types/api.ts";
import { logger } from "../lib/logger.ts";

export function LauncherVisibilitySection() {
    const [builtInLaunchers, setBuiltInLaunchers] = useState<Launcher[]>([]);
    const [hiddenSlugs, setHiddenSlugs] = useState<Set<string>>(new Set());
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [toggling, setToggling] = useState<Set<string>>(new Set());

    const load = useCallback(async () => {
        try {
            // Include hidden launchers so the user can see every built-in and toggle them back.
            const all = await getLaunchers({ includeHidden: true });
            const builtIn = all.filter((l) => !l.is_custom);
            setBuiltInLaunchers(builtIn);

            // Backend returns hidden launcher slugs.
            const hidden = await fetchHiddenLaunchers();
            setHiddenSlugs(new Set(hidden));
            setError("");
        } catch (e) {
            setError(e instanceof Error ? e.message : "Could not load launcher visibility.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const isHidden = (slug: string): boolean => hiddenSlugs.has(slug);

    const handleToggle = async (launcher: Launcher) => {
        const currentlyHidden = isHidden(launcher.slug);
        setToggling((prev) => new Set(prev).add(launcher.slug));
        setError("");

        // Optimistic update
        setHiddenSlugs((prev) => {
            const next = new Set(prev);
            if (currentlyHidden) {
                next.delete(launcher.slug);
            } else {
                next.add(launcher.slug);
            }
            return next;
        });

        try {
            if (currentlyHidden) {
                await unhideLauncher(launcher.slug);
            } else {
                await hideLauncher(launcher.slug);
            }
        } catch (err) {
            // Revert on failure
            setHiddenSlugs((prev) => {
                const next = new Set(prev);
                if (currentlyHidden) {
                    next.add(launcher.slug);
                } else {
                    next.delete(launcher.slug);
                }
                return next;
            });
            logger.warn("Launcher visibility toggle failed", err);
            setError(err instanceof Error ? err.message : "Could not toggle visibility.");
        } finally {
            setToggling((prev) => {
                const next = new Set(prev);
                next.delete(launcher.slug);
                return next;
            });
        }
    };

    if (loading) {
        return <p className="workflow-prompts-loading">Loading launcher visibility…</p>;
    }

    const visibleCount = builtInLaunchers.filter((l) => !hiddenSlugs.has(l.slug)).length;

    return (
        <section className="launcher-visibility" aria-labelledby="launcher-visibility-heading">
            <div className="settings-header workflow-prompts-header">
                <div>
                    <span className="section-kicker">Home page</span>
                    <h3 id="launcher-visibility-heading">Launcher visibility</h3>
                </div>
                <span className="visibility-count">
                    {visibleCount}/{builtInLaunchers.length} visible
                </span>
            </div>
            <p className="workflow-prompts-hint">
                Choose which built-in launchers appear on your home page. Hidden launchers are not
                deleted — you can show them again anytime.
            </p>

            {error ? (
                <p className="auth-error workflow-prompts-error" role="alert">
                    {error}
                </p>
            ) : null}

            <ul className="visibility-list">
                {builtInLaunchers.map((launcher) => {
                    const hidden = isHidden(launcher.slug);
                    const isBusy = toggling.has(launcher.slug);
                    return (
                        <li
                            key={launcher.slug}
                            className={`visibility-item${hidden ? " is-hidden" : ""}`}
                        >
                            <div className="visibility-info">
                                <div className="visibility-name-row">
                                    <span className="visibility-name">{launcher.name}</span>
                                    <code className="visibility-slug">{launcher.slug}</code>
                                </div>
                                {launcher.description && (
                                    <p className="visibility-desc">{launcher.description}</p>
                                )}
                            </div>
                            <button
                                type="button"
                                className={`visibility-toggle${hidden ? " is-hidden" : ""}`}
                                onClick={() => handleToggle(launcher)}
                                disabled={isBusy}
                                aria-pressed={!hidden}
                                aria-label={
                                    hidden ? `Show ${launcher.name}` : `Hide ${launcher.name}`
                                }
                            >
                                {isBusy ? (
                                    <span className="visibility-spinner" />
                                ) : hidden ? (
                                    <>
                                        <EyeOff size={15} /> Hidden
                                    </>
                                ) : (
                                    <>
                                        <Eye size={15} /> Visible
                                    </>
                                )}
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
