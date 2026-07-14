import { ArrowRight, TrendingUp } from "lucide-react";
import { scrollToSelector } from "../lib/scroll.ts";

const TRENDING_REPO = {
    repo: "calcom/cal.com",
    label: "Trending repo",
    launcher: "explain-repository" as const,
};

interface TrendingCardProps {
    setUrl: (url: string) => void;
    setSelected: (slug: string) => void;
}

export function TrendingCard({ setUrl, setSelected }: TrendingCardProps) {
    return (
        <div className="trending-card">
            <TrendingUp size={16} />
            <span className="trending-label">Trending repo</span>
            <button
                type="button"
                onClick={() => {
                    setUrl(`https://github.com/${TRENDING_REPO.repo}`);
                    setSelected(TRENDING_REPO.launcher);
                    scrollToSelector("#launcher");
                }}
            >
                <strong>{TRENDING_REPO.repo}</strong>
                <ArrowRight size={14} />
            </button>
            <span className="trending-hint">Explain repository → get architecture insights</span>
        </div>
    );
}
