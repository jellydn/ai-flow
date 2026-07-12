import {
    ArrowRight,
    Check,
    CheckCircle2,
    CircleDot,
    Clock3,
    GitFork,
    ShieldCheck,
    Sparkles,
    X,
    Zap,
} from 'lucide-react';
import type { Launcher } from '../types/api.ts';
import { launcherMetaBySlug, quickLabel, recentRuns, workflowTitleToSlug } from '../data/launcherMeta.ts';
import { scrollToSelector } from '../lib/scroll.ts';
import { LauncherIcon } from './LauncherIcon.tsx';

interface HomeProps {
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
    launchers: Launcher[];
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
    launchers,
}: HomeProps) {
    const quickLaunchers = launchers.slice(0, 4);

    return (
        <main>
            <section className="hero">
                <div className="eyebrow"><Sparkles size={14} /> AI launchers, ready to run</div>
                <h1>
                    Launch developer
                    <br />
                    <em>workflows, not prompts.</em>
                </h1>
                <p className="hero-copy">
                    Review pull requests, plan issues, explain repositories, and inspect Laravel apps—
                    <br className="desktop-only" />
                    without configuring an agent.
                </p>

                <div className="launcher-card" id="launcher">
                    <div className="step-label"><span>1</span> Paste a GitHub URL</div>
                    <div className={`url-box ${error ? 'has-error' : ''}`}>
                        <GitFork size={22} />
                        <input
                            value={url}
                            onChange={(event) => { setUrl(event.target.value); setError(''); }}
                            onKeyDown={(event) => event.key === 'Enter' && !isLaunching && launch()}
                            placeholder="https://github.com/owner/repository/pull/42"
                            aria-label="GitHub URL"
                        />
                        {url && (
                            <button type="button" className="clear-input" onClick={() => setUrl('')} aria-label="Clear URL">
                                <X size={16} />
                            </button>
                        )}
                    </div>
                    {error && <p className="input-error">{error}</p>}

                    <div className="step-label workflow-label"><span>2</span> Choose a launcher</div>
                    <div className="quick-workflows">
                        {quickLaunchers.map((launcher) => {
                            const meta = launcherMetaBySlug[launcher.slug];
                            return (
                                <button
                                    type="button"
                                    key={launcher.slug}
                                    className={selected === launcher.slug ? 'active' : ''}
                                    onClick={() => setSelected(launcher.slug)}
                                >
                                    {meta && <LauncherIcon icon={meta.icon} tone={meta.tone} size={15} />}
                                    <span>{meta ? quickLabel(launcher.slug, meta.title) : launcher.name}</span>
                                    {selected === launcher.slug && <Check size={13} />}
                                </button>
                            );
                        })}
                    </div>

                    <div className="provider-section">
                        <div className="provider-heading">
                            <strong>AI Provider</strong>
                            <span>Optional</span>
                        </div>
                        <div className="provider-fields">
                            <label>
                                <span>Provider</span>
                                <select value="openai" disabled aria-label="AI provider">
                                    <option value="openai">OpenAI</option>
                                </select>
                            </label>
                            <label>
                                <span>API Key</span>
                                <input
                                    type="password"
                                    value={apiKey}
                                    onChange={(event) => setApiKey(event.target.value)}
                                    placeholder="Leave blank to use server key"
                                    autoComplete="off"
                                    spellCheck="false"
                                />
                            </label>
                        </div>
                        <p>Use your own API key to execute this workflow. It is used only for this execution.</p>
                    </div>

                    <button type="button" className="launch-button" onClick={launch} disabled={isLaunching}>
                        <Zap size={19} fill="currentColor" /> {isLaunching ? 'Starting…' : 'Launch workflow'} <ArrowRight size={19} />
                    </button>
                    <div className="trust-row">
                        <span><ShieldCheck size={15} /> Public repositories only</span>
                        <i />
                        <span><Clock3 size={15} /> Results in under a minute</span>
                    </div>
                </div>

                <div className="hero-proof">
                    <div className="avatar-stack"><span>JD</span><span>MK</span><span>AL</span><span>+2k</span></div>
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
                                className={`workflow-card ${selected === launcher.slug ? 'selected' : ''}`}
                                onClick={() => { setSelected(launcher.slug); scrollToSelector('#launcher'); }}
                            >
                                <div className="card-top">
                                    {meta && <LauncherIcon icon={meta.icon} tone={meta.tone} size={23} />}
                                    {meta?.popular && <span className="popular">Most popular</span>}
                                    {meta?.badge && <span className="laravel-badge">{meta.badge}</span>}
                                </div>
                                <h3>{meta?.title ?? launcher.name}</h3>
                                <p>{meta?.description ?? launcher.description}</p>
                                <div className="card-meta">
                                    <span><Clock3 size={14} /> {meta?.time ?? ''}</span>
                                    <span>{meta?.accepts ?? launcher.input_type}</span>
                                </div>
                                <div className="card-action">Launch workflow <ArrowRight size={17} /></div>
                            </button>
                        );
                    })}
                </div>
            </section>

            <section className="recent-section">
                <div className="recent-heading">
                    <div>
                        <div className="section-kicker">Public results</div>
                        <h2>Recent demo runs</h2>
                    </div>
                    <p>Every run becomes a structured report<br />with a public URL ready to share.</p>
                </div>
                <div className="recent-table">
                    {recentRuns.map((run) => (
                        <button
                            type="button"
                            key={run.repo}
                            onClick={() => {
                                setUrl(`https://github.com/${run.repo}/pull/42`);
                                setSelected(workflowTitleToSlug(run.workflow));
                                scrollToSelector('#launcher');
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
                                {run.findings} {run.workflow === 'Issue Plan' ? 'steps' : 'findings'}
                            </span>
                            <span className="run-time"><Clock3 size={13} /> {run.time}</span>
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
                            <p>Repository, pull request, or issue. If it’s public, we can read it.</p>
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
        </main>
    );
}
