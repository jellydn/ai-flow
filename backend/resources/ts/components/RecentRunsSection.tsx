import { ArrowRight, Clock3, GitFork } from "lucide-react";
import { useEffect, useState } from "react";
import type { RecentRunSummary } from "../services/run.ts";
import { fetchRecentRuns } from "../services/run.ts";
import { recentRuns, workflowTitleToSlug } from "../data/launcherMeta.ts";
import { goto } from "../lib/navigate.ts";
import { scrollToSelector } from "../lib/scroll.ts";
import { TrendingCard } from "./TrendingCard.tsx";

function formatDuration(seconds: number | null): string {
    if (seconds === null) return "—";
    if (seconds < 60) return `${seconds}s`;
    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function formatRisk(risk: string): string {
    return risk === "—" ? risk : risk.charAt(0).toUpperCase() + risk.slice(1);
}

interface RecentRunsSectionProps {
    setUrl: (url: string) => void;
    setSelected: (slug: string) => void;
    navigate: (pathname: string) => void;
}

export function RecentRunsSection({ setUrl, setSelected, navigate }: RecentRunsSectionProps) {
    const [realRuns, setRealRuns] = useState<RecentRunSummary[]>([]);

    useEffect(() => {
        fetchRecentRuns()
            .then(setRealRuns)
            .catch(() => setRealRuns([]));
    }, []);

    const hasRealRuns = realRuns.length > 0;

    return (
        <section className="recent-section">
            <div className="recent-heading">
                <div>
                    <div className="section-kicker">Public results</div>
                    <h2>Recent public runs</h2>
                </div>
                <p>
                    Every run becomes a structured report
                    <br />
                    with a public URL ready to share.
                </p>
            </div>

            <TrendingCard setUrl={setUrl} setSelected={setSelected} />

            <div className="recent-table">
                {hasRealRuns
                    ? realRuns.map((run) => (
                          <a
                              key={run.id}
                              href={`/runs/${run.id}`}
                              onClick={(e) => {
                                  e.preventDefault();
                                  goto(`/runs/${run.id}`, navigate);
                              }}
                          >
                              <span className="run-repo">
                                  <GitFork size={17} />
                                  <strong>{run.repo ?? "github.com"}</strong>
                                  <small>{run.type}</small>
                              </span>
                              <span className="run-workflow">
                                  {run.launcher_name ?? run.launcher_slug ?? "Workflow"}
                              </span>
                              <span className={`run-risk ${run.risk.toLowerCase()}`}>
                                  {formatRisk(run.risk)}
                              </span>
                              <span className="run-findings">
                                  {run.findings_count}{" "}
                                  {run.has_verification_steps ? "steps" : "findings"}
                              </span>
                              <span className="run-time">
                                  <Clock3 size={13} /> {formatDuration(run.duration_seconds)}
                              </span>
                              <ArrowRight size={16} />
                          </a>
                      ))
                    : recentRuns.map((run) => (
                          <button
                              type="button"
                              key={run.repo}
                              onClick={() => {
                                  setUrl(`https://github.com/${run.repo}/pull/42`);
                                  setSelected(workflowTitleToSlug(run.workflow));
                                  scrollToSelector("#launcher");
                              }}
                          >
                              <span className="run-repo">
                                  <GitFork size={17} />
                                  <strong>{run.repo}</strong>
                                  <small>{run.run}</small>
                              </span>
                              <span className="run-workflow">{run.workflow}</span>
                              <span className={`run-risk ${run.risk.toLowerCase()}`}>
                                  {run.risk}
                              </span>
                              <span className="run-findings">
                                  {run.findings}{" "}
                                  {run.workflow === "Issue Plan" ? "steps" : "findings"}
                              </span>
                              <span className="run-time">
                                  <Clock3 size={13} /> {run.time}
                              </span>
                              <ArrowRight size={16} />
                          </button>
                      ))}
            </div>
        </section>
    );
}
