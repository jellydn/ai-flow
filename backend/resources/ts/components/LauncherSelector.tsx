import { Check } from "lucide-react";
import type { Launcher } from "../types/api.ts";
import { launcherMetaBySlug, quickLabel } from "../data/launcherMeta.ts";
import { LauncherIcon } from "./LauncherIcon.tsx";

interface LauncherSelectorProps {
    launchers: Launcher[];
    selected: string;
    setSelected: (slug: string) => void;
}

export function LauncherSelector({ launchers, selected, setSelected }: LauncherSelectorProps) {
    const quickLaunchers = launchers.slice(0, 4);

    return (
        <div className="quick-workflows">
            {quickLaunchers.map((launcher) => {
                const meta = launcherMetaBySlug[launcher.slug];
                return (
                    <button
                        type="button"
                        key={launcher.slug}
                        className={selected === launcher.slug ? "active" : ""}
                        onClick={() => setSelected(launcher.slug)}
                    >
                        {meta && <LauncherIcon icon={meta.icon} tone={meta.tone} size={15} />}
                        <span>{meta ? quickLabel(launcher.slug, meta.title) : launcher.name}</span>
                        {selected === launcher.slug && <Check size={13} />}
                    </button>
                );
            })}
        </div>
    );
}
