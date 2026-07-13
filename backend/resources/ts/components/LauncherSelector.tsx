import { Check } from "lucide-react";
import type { Launcher } from "../types/api.ts";
import { launcherTemplateBySlug } from "../data/aiLauncherConfig.ts";
import { launcherMetaBySlug } from "../data/launcherMeta.ts";
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
                const template = launcherTemplateBySlug[launcher.slug];
                const label = template?.shortLabel ?? launcher.name;
                const description = template?.description ?? launcher.description;
                return (
                    <button
                        type="button"
                        key={launcher.slug}
                        className={selected === launcher.slug ? "active" : ""}
                        onClick={() => setSelected(launcher.slug)}
                        title={description}
                        aria-label={`${label}: ${description}`}
                    >
                        {meta && <LauncherIcon icon={meta.icon} tone={meta.tone} size={15} />}
                        <span>{label}</span>
                        {selected === launcher.slug && <Check size={13} />}
                    </button>
                );
            })}
        </div>
    );
}
