import type { LucideIcon } from 'lucide-react';

interface LauncherIconProps {
    icon: LucideIcon;
    tone: string;
    size?: number;
}

export function LauncherIcon({ icon: Icon, tone, size = 20 }: LauncherIconProps) {
    return (
        <div className={`workflow-icon ${tone}`}>
            <Icon size={size} strokeWidth={2} />
        </div>
    );
}
