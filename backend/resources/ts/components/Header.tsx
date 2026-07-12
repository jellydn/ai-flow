import { ArrowRight, GitFork, Menu, X } from "lucide-react";
import { scrollToSelector } from "../lib/scroll.ts";
import { Logo } from "./Logo.tsx";

interface HeaderProps {
    mobileOpen: boolean;
    setMobileOpen: (open: boolean) => void;
    reset: () => void;
}

export function Header({ mobileOpen, setMobileOpen, reset }: HeaderProps) {
    return (
        <header className="topbar">
            <button
                type="button"
                className="logo-button"
                onClick={reset}
                aria-label="AI Launcher home"
            >
                <Logo />
            </button>
            <nav className={mobileOpen ? "nav open" : "nav"}>
                <button
                    type="button"
                    onClick={() => {
                        setMobileOpen(false);
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
            <button
                type="button"
                className="mobile-menu"
                onClick={() => setMobileOpen(!mobileOpen)}
                aria-label="Toggle menu"
            >
                {mobileOpen ? <X /> : <Menu />}
            </button>
        </header>
    );
}
