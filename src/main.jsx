import React, { useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import {
  ArrowRight,
  Bot,
  Check,
  CheckCircle2,
  CircleDot,
  Clock3,
  Code2,
  Copy,
  GitFork,
  LoaderCircle,
  Menu,
  ShieldCheck,
  Sparkles,
  X,
  Zap,
} from 'lucide-react'
import { ErrorBoundary } from './components/ErrorBoundary.jsx'
import {
  demoExecutionSteps,
  demoFindings,
  recentRuns,
  workflows,
} from './data/workflows.js'
import {
  createRun,
  fetchRun,
  isValidGithubUrl,
  parseGithubRepo,
  shareRunUrl,
  streamRun,
} from './lib/api.js'
import { scrollToSelector } from './lib/scroll.js'
import './styles.css'

const DEMO_MODE = import.meta.env.VITE_DEMO_MODE === 'true'

function Logo() {
  return (
    <div className="logo-wrap">
      <div className="logo-mark"><Zap size={19} strokeWidth={2.8} /></div>
      <span>AI Launcher</span>
    </div>
  )
}

function WorkflowIcon({ workflow, size = 20 }) {
  const Icon = workflow.icon
  return <div className={`workflow-icon ${workflow.tone}`}><Icon size={size} strokeWidth={2} /></div>
}

function App() {
  const [selected, setSelected] = useState('review')
  const [url, setUrl] = useState('')
  const [view, setView] = useState('home')
  const [step, setStep] = useState(0)
  const [copied, setCopied] = useState(false)
  const [error, setError] = useState('')
  const [mobileOpen, setMobileOpen] = useState(false)
  const [runId, setRunId] = useState(null)
  const [runSnapshot, setRunSnapshot] = useState(null)
  const [isLaunching, setIsLaunching] = useState(false)

  const activeWorkflow = workflows.find((item) => item.id === selected)
  const parsedRepo = useMemo(() => parseGithubRepo(url) ?? '', [url])

  useEffect(() => {
    if (DEMO_MODE) return undefined
    const match = window.location.pathname.match(/^\/runs\/([0-9a-f-]+)\/?$/i)
    if (!match) return undefined

    let cancelled = false
    fetchRun(match[1])
      .then((snapshot) => {
        if (cancelled) return
        const workflow = workflows.find((item) => item.slug === snapshot.launcher)
        setSelected(workflow?.id ?? 'review')
        setUrl(snapshot.input?.source_url ?? '')
        setRunId(snapshot.id)
        setRunSnapshot(snapshot)
        setView(snapshot.status === 'completed' ? 'report' : snapshot.status === 'failed' ? 'failed' : 'running')
      })
      .catch((e) => {
        if (!cancelled) setError(e.message || 'Could not load this report.')
      })

    return () => { cancelled = true }
  }, [])

  useEffect(() => {
    if (view !== 'running' || runId) return
    if (step >= demoExecutionSteps.length) {
      const done = setTimeout(() => setView('report'), 650)
      return () => clearTimeout(done)
    }
    const timer = setTimeout(() => setStep((value) => value + 1), 780)
    return () => clearTimeout(timer)
  }, [view, step, runId])

  useEffect(() => {
    if (!runId || view !== 'running') return undefined
    let retryTimer
    let disconnected = false
    const poll = () => {
      retryTimer = setTimeout(async () => {
        try {
          const snapshot = await fetchRun(runId)
          setRunSnapshot(snapshot)
          if (snapshot.status === 'completed' || snapshot.status === 'failed') {
            setView(snapshot.status === 'completed' ? 'report' : 'failed')
            return
          }
        } catch {
          // Keep polling through transient API failures.
        }
        if (disconnected) poll()
      }, 1500)
    }
    const closeStream = streamRun(runId, {
      onSnapshot: (snapshot) => setRunSnapshot(snapshot),
      onTerminal: (snapshot, type) => {
        setRunSnapshot(snapshot)
        setView(type === 'completed' ? 'report' : 'failed')
      },
      onDisconnect: () => {
        disconnected = true
        poll()
      },
    })
    return () => {
      disconnected = false
      clearTimeout(retryTimer)
      closeStream()
    }
  }, [runId, view])

  const launch = async () => {
    const trimmed = url.trim()
    if (!trimmed || !isValidGithubUrl(trimmed)) {
      setError('Enter a valid public GitHub repository, issue, or pull request URL.')
      return
    }
    if (!activeWorkflow?.slug) {
      setError('This workflow is not available on the API yet. Pick Review, Plan, Explain, or Laravel doctor.')
      return
    }
    setError('')
    setStep(0)
    setRunId(null)
    setRunSnapshot(null)

    if (DEMO_MODE) {
      setView('running')
      window.scrollTo({ top: 0, behavior: 'smooth' })
      return
    }

    setIsLaunching(true)
    try {
      const body = await createRun(activeWorkflow.slug, trimmed)
      setRunId(body.id)
      window.history.pushState({}, '', `/runs/${body.id}`)
      setView('running')
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (e) {
      setError(e.message || 'Could not start workflow. Is the API running?')
    } finally {
      setIsLaunching(false)
    }
  }

  const reset = () => {
    window.history.pushState({}, '', '/')
    setView('home')
    setStep(0)
    setUrl('')
    setRunId(null)
    setRunSnapshot(null)
    setError('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const progressLines = runId
    ? (runSnapshot?.progress ?? []).map((line) => [line, ''])
    : demoExecutionSteps

  const progressIndex = runId ? (runSnapshot?.progress?.length ?? 0) : step

  return (
    <div className="app-shell">
      <header className="topbar">
        <button className="logo-button" onClick={reset} aria-label="AI Launcher home"><Logo /></button>
        <nav className={mobileOpen ? 'nav open' : 'nav'}>
          <button type="button" onClick={() => { setView('home'); setMobileOpen(false); scrollToSelector('#workflows') }}>Launchers</button>
          <button type="button" onClick={() => { setMobileOpen(false); scrollToSelector('#how') }}>How it works</button>
          <a href="https://github.com/jellydn/ai-flow" target="_blank" rel="noreferrer"><GitFork size={17} /> GitHub</a>
        </nav>
        <button type="button" className="header-cta" onClick={() => { setView('home'); scrollToSelector('#launcher') }}>Launch a workflow <ArrowRight size={16} /></button>
        <button type="button" className="mobile-menu" onClick={() => setMobileOpen(!mobileOpen)} aria-label="Toggle menu">{mobileOpen ? <X /> : <Menu />}</button>
      </header>

      {view === 'home' && (
        <Home
          selected={selected}
          setSelected={setSelected}
          url={url}
          setUrl={setUrl}
          error={error}
          setError={setError}
          launch={launch}
          isLaunching={isLaunching}
        />
      )}
      {view === 'running' && (
        <Running
          activeWorkflow={activeWorkflow}
          repo={parsedRepo || '…'}
          step={progressIndex}
          executionSteps={progressLines}
          live={Boolean(runId)}
        />
      )}
      {view === 'report' && (
        <Report
          workflow={activeWorkflow}
          repo={parsedRepo}
          copied={copied}
          setCopied={setCopied}
          reset={reset}
          runId={runId}
          result={runSnapshot?.result}
        />
      )}
      {view === 'failed' && (
        <main className="running-page">
          <div className="error-fallback">
            <h1>Workflow failed</h1>
            <p>{runSnapshot?.error || 'The run did not complete. Try again or check the API logs.'}</p>
            <button type="button" onClick={reset}>← New launch</button>
          </div>
        </main>
      )}

      <footer>
        <Logo />
        <p>GitHub in. Answers out.</p>
        <span>Built for developers who have better things to do than prompt engineering.</span>
      </footer>
    </div>
  )
}

function Home({ selected, setSelected, url, setUrl, error, setError, launch, isLaunching }) {
  return (
    <main>
      <section className="hero">
        <div className="eyebrow"><Sparkles size={14} /> AI workflows, ready to launch</div>
        <h1>Launch developer<br /><em>workflows, not prompts.</em></h1>
        <p className="hero-copy">Review pull requests, plan issues, explain repositories, and inspect Laravel apps—<br className="desktop-only" />without configuring an agent.</p>

        <div className="launcher-card" id="launcher">
          <div className="step-label"><span>1</span> Paste a GitHub URL</div>
          <div className={`url-box ${error ? 'has-error' : ''}`}>
            <GitFork size={22} />
            <input
              value={url}
              onChange={(event) => { setUrl(event.target.value); setError('') }}
              onKeyDown={(event) => event.key === 'Enter' && !isLaunching && launch()}
              placeholder="https://github.com/owner/repository/pull/42"
              aria-label="GitHub URL"
            />
            {url && <button type="button" className="clear-input" onClick={() => setUrl('')} aria-label="Clear URL"><X size={16} /></button>}
          </div>
          {error && <p className="input-error">{error}</p>}

          <div className="step-label workflow-label"><span>2</span> Choose a workflow</div>
          <div className="quick-workflows">
            {workflows.slice(0, 4).map((workflow) => (
              <button type="button" key={workflow.id} className={selected === workflow.id ? 'active' : ''} onClick={() => setSelected(workflow.id)}>
                <workflow.icon size={15} />
                <span>{workflow.id === 'review' ? 'Review PR' : workflow.id === 'plan' ? 'Plan fix' : workflow.id === 'explain' ? 'Explain' : 'Laravel doctor'}</span>
                {selected === workflow.id && <Check size={13} />}
              </button>
            ))}
          </div>
          <button type="button" className="launch-button" onClick={launch} disabled={isLaunching}>
            <Zap size={19} fill="currentColor" /> {isLaunching ? 'Starting…' : 'Launch workflow'} <ArrowRight size={19} />
          </button>
          <div className="trust-row"><span><ShieldCheck size={15} /> Public repositories only</span><i /><span><Clock3 size={15} /> Results in under a minute</span></div>
        </div>

        <div className="hero-proof">
          <div className="avatar-stack"><span>JD</span><span>MK</span><span>AL</span><span>+2k</span></div>
          <p><strong>Built for focused developers</strong><br />Less prompting. More shipping.</p>
        </div>
      </section>

      <section className="workflow-section" id="workflows">
        <div className="section-kicker">Launcher library</div>
        <h2>Pick a job. <em>Launch it.</em></h2>
        <p>Battle-tested workflows for the work developers do every day.</p>
        <div className="workflow-grid">
          {workflows.map((workflow) => (
            <button
              type="button"
              key={workflow.id}
              className={`workflow-card ${selected === workflow.id ? 'selected' : ''}`}
              onClick={() => { setSelected(workflow.id); scrollToSelector('#launcher') }}
            >
              <div className="card-top"><WorkflowIcon workflow={workflow} size={23} />{workflow.popular && <span className="popular">Most popular</span>}{workflow.badge && <span className="laravel-badge">{workflow.badge}</span>}</div>
              <h3>{workflow.title}</h3>
              <p>{workflow.description}</p>
              <div className="card-meta"><span><Clock3 size={14} /> {workflow.time}</span><span>{workflow.accepts}</span></div>
              <div className="card-action">Launch workflow <ArrowRight size={17} /></div>
            </button>
          ))}
        </div>
      </section>

      <section className="recent-section">
        <div className="recent-heading"><div><div className="section-kicker">Public results</div><h2>Recent demo runs</h2></div><p>Every run becomes a structured report<br />with a public URL ready to share.</p></div>
        <div className="recent-table">
          {recentRuns.map((run) => (
            <button
              type="button"
              key={run.repo}
              onClick={() => {
                setUrl(`https://github.com/${run.repo}/pull/42`)
                setSelected(run.workflow === 'Laravel Doctor' ? 'doctor' : run.workflow === 'Issue Plan' ? 'plan' : 'review')
                scrollToSelector('#launcher')
              }}
            >
              <span className="run-repo"><GitFork size={17} /><strong>{run.repo}</strong><small>{run.run}</small></span>
              <span className="run-workflow">{run.workflow}</span>
              <span className={`run-risk ${run.risk.toLowerCase()}`}>{run.risk}</span>
              <span className="run-findings">{run.findings} {run.workflow === 'Issue Plan' ? 'steps' : 'findings'}</span>
              <span className="run-time"><Clock3 size={13} /> {run.time}</span>
              <ArrowRight size={16} />
            </button>
          ))}
        </div>
      </section>

      <section className="how-section" id="how">
        <div className="how-copy">
          <div className="section-kicker">How it works</div>
          <h2>From URL to insight<br />in <em>three steps.</em></h2>
          <p>No setup. No prompt templates. No context juggling.</p>
        </div>
        <div className="steps-list">
          <div><span>01</span><section><h3>Paste your GitHub URL</h3><p>Repository, pull request, or issue. If it’s public, we can read it.</p></section><GitFork /></div>
          <div><span>02</span><section><h3>Choose a launcher</h3><p>Pick the expert workflow that matches the job you need done.</p></section><CircleDot /></div>
          <div><span>03</span><section><h3>Get a structured report</h3><p>Clear findings, concrete actions, and a link ready to share.</p></section><CheckCircle2 /></div>
        </div>
      </section>
    </main>
  )
}

function Running({ activeWorkflow, repo, step, executionSteps, live }) {
  const total = Math.max(executionSteps.length, 1)
  const progress = Math.min(100, Math.round((step / total) * 100))
  return (
    <main className="running-page">
      <div className="run-heading">
        <div className="running-pulse"><Bot size={22} /></div>
        <div><div className="eyebrow">Workflow in progress</div><h1>Analyzing <em>{repo}</em></h1><p>{activeWorkflow?.title} · This usually takes less than a minute</p></div>
      </div>
      <div className="progress-card">
        <div className="progress-head"><span>AI analysis</span><strong>{progress}%</strong></div>
        <div className="progress-track"><span style={{ width: `${progress}%` }} /></div>
        <div className="timeline">
          {executionSteps.map(([title, detail], index) => {
            const complete = index < step
            const current = index === step
            return (
              <div className={`timeline-row ${complete ? 'complete' : ''} ${current ? 'current' : ''}`} key={`${title}-${index}`}>
                <div className="status-icon">{complete ? <Check size={16} /> : current ? <LoaderCircle size={16} className="spin" /> : <span />}</div>
                <div><strong>{title}</strong><p>{complete || current ? (detail || (live ? 'In progress…' : 'Working…')) : 'Waiting…'}</p></div>
                {complete && <span className="done-label">Done</span>}
              </div>
            )
          })}
        </div>
      </div>
      <p className="running-note"><ShieldCheck size={16} /> GitHub context is used for analysis and cleared from storage after the report is ready.</p>
    </main>
  )
}

function Report({ workflow, repo, copied, setCopied, reset, runId, result }) {
  const useDemo = !result
  const findings = useDemo
    ? demoFindings
    : (result.findings ?? []).map((f) => ({
        severity: f.severity,
        title: f.title,
        file: null,
        body: f.description,
        fix: f.recommendation,
      }))
  const summary = useDemo
    ? 'This pull request introduces useful filtering and organization features, but contains one authorization vulnerability that should be fixed before merging.'
    : result.summary
  const risk = useDemo ? 'medium' : (result.risk ?? 'medium')
  const checklist = useDemo
    ? ['Add authorization policy check before deleting tools', 'Replace usage counter update with atomic increment', 'Add feature tests for combined filters', 'Run the full test suite before merge']
    : (result.verification_steps ?? [])

  const copy = async () => {
    const link = runId ? shareRunUrl(runId) : `${window.location.origin}/runs/demo`
    await navigator.clipboard?.writeText(link)
    setCopied(true)
    setTimeout(() => setCopied(false), 1800)
  }

  return (
    <main className="report-page">
      <div className="report-topline"><button type="button" className="back-button" onClick={reset}>← New launch</button><div className="share-actions"><button type="button" onClick={copy}>{copied ? <Check size={16} /> : <Copy size={16} />}{copied ? 'Copied' : 'Copy link'}</button><button type="button" className="primary-share">Share report <ArrowRight size={16} /></button></div></div>
      <section className="report-hero">
        <div className="report-status"><CheckCircle2 size={16} /> Analysis complete</div>
        <h1>{workflow?.title}</h1>
        <div className="repo-name"><GitFork size={18} /> {repo || 'repository'}</div>
        <div className="report-stats">
          <div><span>Risk level</span><strong className="risk"><CircleDot size={15} /> {risk}</strong></div>
          <div><span>Findings</span><strong>{findings.length}</strong></div>
        </div>
      </section>

      <div className="report-layout">
        <aside>
          <span>On this page</span>
          <a href="#summary" className="active">Executive summary</a>
          <a href="#findings">Key findings <b>{findings.length}</b></a>
          <a href="#checklist">Verification checklist</a>
          <div className="ai-card"><Sparkles size={18} /><strong>AI-generated report</strong><p>Always verify critical findings before merging.</p></div>
        </aside>
        <article className="report-content">
          <section id="summary">
            <div className="content-heading"><span>01</span><h2>Executive summary</h2></div>
            <div className="summary-box"><p>{summary}</p></div>
          </section>
          <section id="findings">
            <div className="content-heading"><span>02</span><h2>Key findings</h2><b>{findings.length} findings</b></div>
            <div className="findings-list">
              {findings.map((finding, index) => (
                <div className="finding" key={`${finding.title}-${index}`}>
                  <div className="finding-header"><span className={`severity ${finding.severity}`}>{finding.severity}</span><span className="finding-number">0{index + 1}</span></div>
                  <h3>{finding.title}</h3>
                  {finding.file && <code><Code2 size={14} /> {finding.file}</code>}
                  <p>{finding.body}</p>
                  <div className="suggestion"><strong><Sparkles size={14} /> Suggested fix</strong><p>{finding.fix}</p></div>
                </div>
              ))}
            </div>
          </section>
          <section id="checklist">
            <div className="content-heading"><span>03</span><h2>Verification checklist</h2></div>
            <div className="checklist">
              {checklist.map((item, index) => <label key={item}><input type="checkbox" defaultChecked={index === checklist.length - 1} /><span>{item}</span></label>)}
            </div>
          </section>
        </article>
      </div>
    </main>
  )
}

createRoot(document.getElementById('root')).render(
  <ErrorBoundary>
    <App />
  </ErrorBoundary>,
)
