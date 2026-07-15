import { ArrowRight, GitFork, Menu, X } from "lucide-react";
import { scrollToSelector } from "../lib/scroll.ts";
import type { User } from "../services/auth.ts";
import { Logo } from "./Logo.tsx";

interface HeaderProps {
    mobileOpen: boolean;
    setMobileOpen: (open: boolean) => void;
    reset: () => void;
    user: User | null;
    onAuthClick: () => void;
    onLaunchClick?: () => void;
}

export function Header({
    mobileOpen,
    setMobileOpen,
    reset,
    user,
    onAuthClick,
    onLaunchClick,
}: HeaderProps) {
    return (
        <header className="topbar">
            <button type="button" className="logo-button" onClick={reset} aria-label="AI Flow home">
                <Logo />
            </button>
            <nav className={mobileOpen ? "nav open" : "nav"}>
                <button
                    type="button"
                    onClick={() => {
                        setMobileOpen(false);
                        if (user && onLaunchClick) {
                            onLaunchClick();
                            return;
                        }
                        reset();
                        scrollToSelector("#workflows");
                    }}
                >
                    Launchers
                </button>
                <button
                    type="button"
                    onClick={() => {
                        setMobileOpen(false);
                        reset();
                        scrollToSelector("#how");
                    }}
                >
                    How it works
                </button>
                <a href="https://github.com/jellydn/ai-flow" target="_blank" rel="noreferrer">
                    <GitFork size={17} /> GitHub
                </a>
            </nav>
            <div className="header-end">
                <div className="header-actions">
                    {user ? (
                        <button
                            type="button"
                            className="header-cta"
                            onClick={() => {
                                setMobileOpen(false);
                                onAuthClick();
                            }}
                            title="Account and run history"
                        >
                            {user.email}
                        </button>
                    ) : (
                        <>
                            <button
                                type="button"
                                className="header-cta"
                                onClick={() => {
                                    reset();
                                    scrollToSelector("#launcher");
                                }}
                            >
                                Launch a workflow <ArrowRight size={16} />
                            </button>
                            <button type="button" className="header-auth-btn" onClick={onAuthClick}>
                                Sign in
                            </button>
                        </>
                    )}
                </div>
                <button
                    type="button"
                    className="mobile-menu"
                    onClick={() => setMobileOpen(!mobileOpen)}
                    aria-label="Toggle menu"
                >
                    {mobileOpen ? <X /> : <Menu />}
                </button>
            </div>
        </header>
    );
}
