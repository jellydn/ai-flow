import {
    ArrowRight,
    CheckCircle2,
    CircleDot,
    Clock3,
    GitFork,
    KeyRound,
    Share2,
    Sparkles,
    Zap,
} from "lucide-react";
import type { Launcher } from "../types/api.ts";
import type { RunProviderId } from "../services/run.ts";
import type { ProviderCredential } from "../services/auth.ts";
import { launcherMetaBySlug, recentRuns, workflowTitleToSlug } from "../data/launcherMeta.ts";
import { scrollToSelector } from "../lib/scroll.ts";
import { LauncherIcon } from "./LauncherIcon.tsx";
import { FlowMark } from "./Logo.tsx";
import { LauncherSelector } from "./LauncherSelector.tsx";
import { LaunchArea } from "./LaunchArea.tsx";
import { UrlInput } from "./UrlInput.tsx";

const features = [
    {
        icon: Zap,
        title: "Workflows, not prompts",
        body: "Pick a battle-tested launcher and run it. No prompt engineering, no context juggling.",
    },
    {
        icon: CheckCircle2,
        title: "Structured, validated output",
        body: "Every run returns a schema-validated report—clear findings and concrete next actions.",
    },
    {
        icon: Share2,
        title: "Shareable result URLs",
        body: "Each run gets a public /runs/:id link, ready to drop into a PR, issue, or Slack thread.",
    },
    {
        icon: KeyRound,
        title: "Bring your own key",
        body: "Use the server key or paste your own—it is used only for that single execution.",
    },
];

export interface HomeProps {
    selected: string;
    setSelected: (slug: string) => void;
    url: string;
    setUrl: (url: string) => void;
    error: string;
    setError: (error: string) => void;
    launch: () => void;
    isLaunching: boolean;
    apiKey: string;
    setApiKey: (key: string) => void;
    selectedProvider: RunProviderId;
    setSelectedProvider: (provider: RunProviderId) => void;
    launchers: Launcher[];
    credentials?: ProviderCredential[];
    selectedCredentialId?: string | null;
    setSelectedCredentialId?: (id: string | null) => void;
}

export function Home({
    selected,
    setSelected,
    url,
    setUrl,
    error,
    setError,
    launch,
    isLaunching,
    apiKey,
    setApiKey,
    selectedProvider,
    setSelectedProvider,
    launchers,
    credentials,
    selectedCredentialId,
    setSelectedCredentialId,
}: HomeProps) {
    return (
        <main>
            <section className="hero">
                <div className="eyebrow">
                    <Sparkles size={14} /> AI Flow — workflows, ready to run
                </div>
                <h1>
                    Put your GitHub URLs
                    <br />
                    <em>in flow.</em>
                </h1>
                <p className="hero-copy">
                    AI Flow reviews pull requests, plans issues, explains repositories, and inspects
                    Laravel apps—
                    <br className="desktop-only" />
                    without configuring an agent or writing a single prompt.
                </p>

                <div className="launcher-card" id="launcher">
                    <div className="step-label">
                        <span>1</span> Paste a GitHub URL
                    </div>
                    <UrlInput
                        url={url}
                        setUrl={setUrl}
                        error={error}
                        setError={setError}
                        launch={launch}
                        isLaunching={isLaunching}
                    />

                    <div className="step-label workflow-label">
                        <span>2</span> Choose a launcher
                    </div>
                    <LauncherSelector
                        launchers={launchers}
                        selected={selected}
                        setSelected={setSelected}
                    />

                    <LaunchArea
                        provider={selectedProvider}
                        setProvider={setSelectedProvider}
                        apiKey={apiKey}
                        setApiKey={setApiKey}
                        launch={launch}
                        isLaunching={isLaunching}
                        credentials={credentials}
                        selectedCredentialId={selectedCredentialId}
                        setSelectedCredentialId={setSelectedCredentialId}
                    />
                </div>

                <div className="hero-proof">
                    <div className="avatar-stack">
                        <span>JD</span>
                        <span>MK</span>
                        <span>AL</span>
                        <span>+2k</span>
                    </div>
                    <p>
                        <strong>Built for focused developers</strong>
                        <br />
                        Less prompting. More shipping.
                    </p>
                </div>
            </section>

            <section className="workflow-section" id="workflows">
                <div className="section-kicker">Launcher library</div>
                <h2>
                    Pick a job. <em>Launch it.</em>
                </h2>
                <p>Battle-tested workflows for the work developers do every day.</p>
                <div className="workflow-grid">
                    {launchers.map((launcher) => {
                        const meta = launcherMetaBySlug[launcher.slug];
                        return (
                            <button
                                type="button"
                                key={launcher.slug}
                                className={`workflow-card ${selected === launcher.slug ? "selected" : ""}`}
                                onClick={() => {
                                    setSelected(launcher.slug);
                                    scrollToSelector("#launcher");
                                }}
                            >
                                <div className="card-top">
                                    {meta && (
                                        <LauncherIcon icon={meta.icon} tone={meta.tone} size={23} />
                                    )}
                                    {meta?.popular && <span className="popular">Most popular</span>}
                                    {meta?.badge && (
                                        <span className="laravel-badge">{meta.badge}</span>
                                    )}
                                </div>
                                <h3>{meta?.title ?? launcher.name}</h3>
                                <p>{meta?.description ?? launcher.description}</p>
                                <div className="card-meta">
                                    <span>
                                        <Clock3 size={14} /> {meta?.time ?? ""}
                                    </span>
                                    <span>{meta?.accepts ?? launcher.input_type}</span>
                                </div>
                                <div className="card-action">
                                    Launch workflow <ArrowRight size={17} />
                                </div>
                            </button>
                        );
                    })}
                </div>
            </section>

            <section className="features-section" id="features">
                <div className="section-kicker">Why AI Flow</div>
                <h2>
                    Less prompting. <em>More shipping.</em>
                </h2>
                <div className="features-grid">
                    {features.map((feature) => (
                        <div className="feature-card" key={feature.title}>
                            <div className="feature-icon">
                                <feature.icon size={20} />
                            </div>
                            <h3>{feature.title}</h3>
                            <p>{feature.body}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="recent-section">
                <div className="recent-heading">
                    <div>
                        <div className="section-kicker">Public results</div>
                        <h2>Recent demo runs</h2>
                    </div>
                    <p>
                        Every run becomes a structured report
                        <br />
                        with a public URL ready to share.
                    </p>
                </div>
                <div className="recent-table">
                    {recentRuns.map((run) => (
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
                            <span className={`run-risk ${run.risk.toLowerCase()}`}>{run.risk}</span>
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

            <section className="how-section" id="how">
                <div className="how-copy">
                    <div className="section-kicker">How it works</div>
                    <h2>
                        From URL to insight
                        <br />
                        in <em>three steps.</em>
                    </h2>
                    <p>No setup. No prompt templates. No context juggling.</p>
                </div>
                <div className="steps-list">
                    <div>
                        <span>01</span>
                        <section>
                            <h3>Paste your GitHub URL</h3>
                            <p>
                                Repository, pull request, or issue. If it’s public, we can read it.
                            </p>
                        </section>
                        <GitFork />
                    </div>
                    <div>
                        <span>02</span>
                        <section>
                            <h3>Choose a launcher</h3>
                            <p>Pick the expert workflow that matches the job you need done.</p>
                        </section>
                        <CircleDot />
                    </div>
                    <div>
                        <span>03</span>
                        <section>
                            <h3>Get a structured report</h3>
                            <p>Clear findings, concrete actions, and a link ready to share.</p>
                        </section>
                        <CheckCircle2 />
                    </div>
                </div>
            </section>

            <section className="cta-band">
                <div className="cta-mark">
                    <FlowMark size={30} />
                </div>
                <h2>
                    Ready to put your repo <em>in flow?</em>
                </h2>
                <p>
                    Paste a GitHub URL, pick a launcher, and get a shareable report in under a
                    minute.
                </p>
                <button
                    type="button"
                    className="launch-button cta-button"
                    onClick={() => scrollToSelector("#launcher")}
                >
                    <Zap size={19} fill="currentColor" /> Launch a workflow <ArrowRight size={19} />
                </button>
            </section>
        </main>
    );
}
