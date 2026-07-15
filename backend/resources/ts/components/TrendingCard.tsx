import { ArrowRight, TrendingUp } from "lucide-react";
import { useEffect, useState } from "react";
import { scrollToSelector } from "../lib/scroll.ts";
import { fetchTrendingRepositories, type TrendingRepositorySummary } from "../services/run.ts";

const EXPLAIN_REPOSITORY_SLUG = "explain-repository" as const;

interface TrendingCardProps {
    setUrl: (url: string) => void;
    setSelected: (slug: string) => void;
}

export function TrendingCard({ setUrl, setSelected }: TrendingCardProps) {
    const [repos, setRepos] = useState<TrendingRepositorySummary[]>([]);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        fetchTrendingRepositories()
            .then(setRepos)
            .catch(() => setRepos([]))
            .finally(() => setLoaded(true));
    }, []);

    if (!loaded || repos.length === 0) {
        return null;
    }

    const applyRepo = (item: TrendingRepositorySummary) => {
        setUrl(item.url);
        setSelected(EXPLAIN_REPOSITORY_SLUG);
        scrollToSelector("#launcher");
    };

    return (
        <div className="trending-card">
            <TrendingUp size={16} />
            <span className="trending-label">Trending today</span>
            <div className="trending-actions">
                {repos.map((item) => (
                    <button key={item.repo} type="button" onClick={() => applyRepo(item)}>
                        <strong>{item.repo}</strong>
                        <ArrowRight size={14} />
                    </button>
                ))}
            </div>
            <span className="trending-hint">Explain repository → get architecture insights</span>
        </div>
    );
}
