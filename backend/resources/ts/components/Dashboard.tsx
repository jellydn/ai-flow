import { useState } from "react";
import { deleteAccount, logout, type User } from "../services/auth.ts";
import { goto } from "../lib/navigate.ts";
import { logger } from "../lib/logger.ts";
import { ProviderSettings } from "./ProviderSettings.tsx";
import { RunHistory } from "./RunHistory.tsx";

interface DashboardProps {
    user: User;
    onLogout: () => void;
    navigate: (pathname: string) => void;
}

type Tab = "providers" | "history" | "account";

export function Dashboard({ user, onLogout, navigate }: DashboardProps) {
    const [tab, setTab] = useState<Tab>("history");
    const [loggingOut, setLoggingOut] = useState(false);
    const [deletingAccount, setDeletingAccount] = useState(false);
    const [accountError, setAccountError] = useState("");
    const [confirmDelete, setConfirmDelete] = useState(false);

    const handleLogout = async () => {
        setLoggingOut(true);
        try {
            await logout();
        } catch (error) {
            // Log the failure but still sign out locally — onLogout runs
            // in finally regardless, so the user is not stuck.
            logger.error("Logout request failed:", error);
        } finally {
            onLogout();
        }
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: "history", label: "Run History" },
        { key: "providers", label: "API Keys" },
        { key: "account", label: "Account" },
    ];

    return (
        <main className="dashboard" id="account">
            <div className="dashboard-header">
                <div>
                    <h2>Your account</h2>
                    <p className="user-email">{user.email}</p>
                    <p className="dashboard-hint">
                        Run history lists workflows you started while signed in.{" "}
                        <button
                            type="button"
                            className="auth-link"
                            onClick={() => goto("/", navigate)}
                        >
                            Launch a new workflow
                        </button>
                    </p>
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
            <div className="dashboard-tabs" role="tablist" aria-label="Account sections">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
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
                {tab === "account" && (
                    <div className="account-settings">
                        <h3>Privacy &amp; Data</h3>
                        <div className="privacy-panel">
                            <p>
                                Your API keys are encrypted before being stored. They are decrypted
                                only when an AI request is sent to your selected provider. Keys are
                                never shown again after saving. You can replace or delete them at
                                any time.
                            </p>
                            <p>
                                When you run a flow, relevant input is sent to your selected AI
                                provider. GitHub repository or issue content may be sent to that
                                provider. Do not submit secrets or confidential code unless
                                authorized.
                            </p>
                        </div>

                        <div className="danger-zone">
                            <h4>Delete account</h4>
                            <p>
                                Permanently delete your account, all saved credentials, and all run
                                history. This action cannot be undone.
                            </p>
                            <label className="checklist">
                                <input
                                    type="checkbox"
                                    checked={confirmDelete}
                                    onChange={(e) => setConfirmDelete(e.target.checked)}
                                />
                                I understand this action is permanent and cannot be undone.
                            </label>
                            <button
                                type="button"
                                className="danger-btn"
                                onClick={async () => {
                                    if (!confirmDelete) return;
                                    setDeletingAccount(true);
                                    setAccountError("");
                                    try {
                                        await deleteAccount();
                                        onLogout();
                                    } catch (e) {
                                        setAccountError(
                                            e instanceof Error
                                                ? e.message
                                                : "Failed to delete account.",
                                        );
                                        setDeletingAccount(false);
                                    }
                                }}
                                disabled={!confirmDelete || deletingAccount}
                            >
                                {deletingAccount ? "Deleting…" : "Delete my account"}
                            </button>
                            {accountError && <p className="auth-error">{accountError}</p>}
                        </div>
                    </div>
                )}
            </div>
        </main>
    );
}
