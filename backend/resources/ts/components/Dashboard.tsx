import { useState } from "react";
import { logout, type User } from "../services/auth.ts";
import { ProviderSettings } from "./ProviderSettings.tsx";
import { RunHistory } from "./RunHistory.tsx";

interface DashboardProps {
    user: User;
    onLogout: () => void;
    navigate: (pathname: string) => void;
}

type Tab = "providers" | "history";

export function Dashboard({ user, onLogout, navigate }: DashboardProps) {
    const [tab, setTab] = useState<Tab>("history");
    const [loggingOut, setLoggingOut] = useState(false);

    const handleLogout = async () => {
        setLoggingOut(true);
        try {
            await logout();
        } finally {
            onLogout();
        }
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: "history", label: "Run History" },
        { key: "providers", label: "API Keys" },
    ];

    return (
        <div className="dashboard">
            <div className="dashboard-header">
                <div>
                    <h2>Dashboard</h2>
                    <p className="user-email">{user.email}</p>
                </div>
                <button
                    type="button"
                    className="logout-btn"
                    onClick={handleLogout}
                    disabled={loggingOut}
                >
                    {loggingOut ? "Signing out…" : "Sign out"}
                </button>
            </div>
            <div className="dashboard-tabs">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        className={tab === t.key ? "tab active" : "tab"}
                        onClick={() => setTab(t.key)}
                    >
                        {t.label}
                    </button>
                ))}
            </div>
            <div className="dashboard-content">
                {tab === "history" && <RunHistory navigate={navigate} />}
                {tab === "providers" && <ProviderSettings />}
            </div>
        </div>
    );
}
