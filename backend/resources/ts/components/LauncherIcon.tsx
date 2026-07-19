import {
    Award,
    BookOpen,
    Crosshair,
    GitPullRequest,
    ListTodo,
    type LucideIcon,
    Sparkles,
    Star,
    Stethoscope,
    Target,
    Zap,
} from "lucide-react";

interface LauncherIconProps {
    icon: LucideIcon | string | undefined;
    tone: string;
    size?: number;
}

const ICON_MAP: Record<string, LucideIcon> = {
    GitPullRequest,
    ListTodo,
    BookOpen,
    Stethoscope,
    Sparkles,
    Zap,
    Star,
    Target,
    Crosshair,
    Award,
};

export function LauncherIcon({ icon, tone, size = 20 }: LauncherIconProps) {
    const Icon = typeof icon === "string" ? (ICON_MAP[icon] ?? Sparkles) : (icon ?? Sparkles);

    return (
        <div className={`workflow-icon ${tone}`}>
            <Icon size={size} strokeWidth={2} />
        </div>
    );
}
