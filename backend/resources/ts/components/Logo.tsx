import { Zap } from "lucide-react";

export function Logo() {
    return (
        <div className="logo-wrap">
            <div className="logo-mark">
                <Zap size={19} strokeWidth={2.8} />
            </div>
            <span>AI Launcher</span>
        </div>
    );
}
