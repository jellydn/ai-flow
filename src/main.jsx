import React, { useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import {
  ArrowRight,
  BookOpen,
  Bot,
  Check,
  CheckCircle2,
  CircleDot,
  Clock3,
  Code2,
  Copy,
  GitFork,
  GitPullRequest,
  ListTodo,
  LoaderCircle,
  Menu,
  Newspaper,
  ShieldCheck,
  Sparkles,
  Stethoscope,
  X,
  Zap,
} from 'lucide-react'
import './styles.css'

const workflows = [
  {
    id: 'review',
    title: 'Review pull request',
    description: 'Find bugs, security risks, and regressions before they ship.',
    icon: GitPullRequest,
    tone: 'orange',
    time: '~45 sec',
    accepts: 'Pull requests',
    popular: true,
  },
  {
    id: 'plan',
    title: 'Plan GitHub issue',
    description: 'Turn an issue into a scoped, actionable implementation plan.',
    icon: ListTodo,
    tone: 'blue',
    time: '~30 sec',
    accepts: 'Issues',
  },
  {
    id: 'explain',
    title: 'Explain repository',
    description: 'Understand architecture, key modules, and how everything fits.',
    icon: BookOpen,
    tone: 'purple',
    time: '~55 sec',
    accepts: 'Repositories',
  },
  {
    id: 'doctor',
    title: 'Laravel project doctor',
    description: 'Audit Laravel conventions, performance, and project health.',
    icon: Stethoscope,
    tone: 'green',
    time: '~60 sec',
    accepts: 'Repositories',
    badge: 'Laravel',
  },
  {
    id: 'release',
    title: 'Write release notes',
    description: 'Turn a pull request or commit range into clear user-facing notes.',
    icon: Newspaper,
    tone: 'blue',
    time: '~25 sec',
    accepts: 'PRs or commits',
  },
  {
    id: 'security',
    title: 'Security scan',
    description: 'Run a focused pass for auth, input, and data exposure risks.',
    icon: ShieldCheck,
    tone: 'purple',
    time: '~50 sec',
    accepts: 'Pull requests',
  },
]

const recentRuns = [
  { repo: 'jellydn/my-ai-tools', run: 'Pull request #42', workflow: 'PR Review', risk: 'Medium', findings: 5, time: '34s' },
  { repo: 'laravel/framework', run: 'Repository', workflow: 'Laravel Doctor', risk: 'Low', findings: 3, time: '52s' },
  { repo: 'calcom/cal.com', run: 'Issue #20418', workflow: 'Issue Plan', risk: '—', findings: 8, time: '29s' },
]

const executionSteps = [
  ['Reading GitHub metadata', 'Pull request #42 · 12 files changed'],
  ['Loading source context', '2,840 lines analyzed'],
  ['Running AI analysis', 'Reviewing logic, security, and test coverage'],
  ['Validating response', 'Checking findings and citations'],
  ['Generating report', 'Formatting your shareable result'],
]

const findings = [
  {
    severity: 'high',
    title: 'Missing authorization check on tool deletion',
    file: 'app/Http/Controllers/ToolController.php:84',
    body: 'The destroy action loads a tool by ID but does not verify that it belongs to the authenticated user. A user could delete another user’s tool by changing the route parameter.',
    fix: 'Add a policy check with $this->authorize(\'delete\', $tool) before deletion.',
  },
  {
    severity: 'medium',
    title: 'Race condition when updating usage counters',
    file: 'app/Services/UsageTracker.php:31',
    body: 'The read-modify-write sequence is not atomic. Concurrent requests can overwrite each other and undercount usage.',
    fix: 'Use Eloquent’s atomic increment() method inside the existing transaction.',
  },
  {
    severity: 'low',
    title: 'New filtering behavior has no test coverage',
    file: 'tests/Feature/ToolIndexTest.php',
    body: 'The new category and status filters are user-facing but are not covered by feature tests.',
    fix: 'Add cases for combined filters, empty results, and invalid category values.',
  },
]

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
  const activeWorkflow = workflows.find((item) => item.id === selected)

  const parsedRepo = useMemo(() => {
    const match = url.match(/github\.com\/([^/]+)\/([^/#?]+)/i)
    return match ? `${match[1]}/${match[2].replace(/\.git$/, '')}` : 'jellydn/my-ai-tools'
  }, [url])

  useEffect(() => {
    if (view !== 'running') return
    if (step >= executionSteps.length) {
      const done = setTimeout(() => setView('report'), 650)
      return () => clearTimeout(done)
    }
    const timer = setTimeout(() => setStep((value) => value + 1), 780)
    return () => clearTimeout(timer)
  }, [view, step])

  const launch = () => {
    if (!/^https?:\/\/(www\.)?github\.com\/[^/]+\/[^/]+/i.test(url)) {
      setError('Enter a valid public GitHub repository, issue, or pull request URL.')
      return
    }
    setError('')
    setStep(0)
    setView('running')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const reset = () => {
    setView('home')
    setStep(0)
    setUrl('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  return (
    <div className="app-shell">
      <header className="topbar">
        <button className="logo-button" onClick={reset} aria-label="AI Launcher home"><Logo /></button>
        <nav className={mobileOpen ? 'nav open' : 'nav'}>
          <button onClick={() => { setView('home'); setMobileOpen(false); setTimeout(() => document.querySelector('#workflows')?.scrollIntoView({ behavior: 'smooth' }), 0) }}>Launchers</button>
          <button onClick={() => { setMobileOpen(false); document.querySelector('#how')?.scrollIntoView({ behavior: 'smooth' }) }}>How it works</button>
          <a href="https://github.com" target="_blank" rel="noreferrer"><GitFork size={17} /> GitHub</a>
        </nav>
        <button className="header-cta" onClick={() => { setView('home'); setTimeout(() => document.querySelector('#launcher')?.scrollIntoView({ behavior: 'smooth' }), 0) }}>Launch a workflow <ArrowRight size={16} /></button>
        <button className="mobile-menu" onClick={() => setMobileOpen(!mobileOpen)} aria-label="Toggle menu">{mobileOpen ? <X /> : <Menu />}</button>
      </header>

      {view === 'home' && <Home selected={selected} setSelected={setSelected} url={url} setUrl={setUrl} error={error} setError={setError} launch={launch} />}
      {view === 'running' && <Running activeWorkflow={activeWorkflow} repo={parsedRepo} step={step} />}
      {view === 'report' && <Report workflow={activeWorkflow} repo={parsedRepo} copied={copied} setCopied={setCopied} reset={reset} />}

      <footer>
        <Logo />
        <p>GitHub in. Answers out.</p>
        <span>Built for developers who have better things to do than prompt engineering.</span>
      </footer>
    </div>
  )
}

function Home({ selected, setSelected, url, setUrl, error, setError, launch }) {
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
              onKeyDown={(event) => event.key === 'Enter' && launch()}
              placeholder="https://github.com/owner/repository/pull/42"
              aria-label="GitHub URL"
            />
            {url && <button className="clear-input" onClick={() => setUrl('')} aria-label="Clear URL"><X size={16} /></button>}
          </div>
          {error && <p className="input-error">{error}</p>}

          <div className="step-label workflow-label"><span>2</span> Choose a workflow</div>
          <div className="quick-workflows">
            {workflows.slice(0, 4).map((workflow) => (
              <button key={workflow.id} className={selected === workflow.id ? 'active' : ''} onClick={() => setSelected(workflow.id)}>
                <workflow.icon size={15} />
                <span>{workflow.id === 'review' ? 'Review PR' : workflow.id === 'plan' ? 'Plan fix' : workflow.id === 'explain' ? 'Explain' : 'Laravel doctor'}</span>
                {selected === workflow.id && <Check size={13} />}
              </button>
            ))}
          </div>
          <button className="launch-button" onClick={launch}><Zap size={19} fill="currentColor" /> Launch workflow <ArrowRight size={19} /></button>
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
            <button key={workflow.id} className={`workflow-card ${selected === workflow.id ? 'selected' : ''}`} onClick={() => { setSelected(workflow.id); document.querySelector('#launcher')?.scrollIntoView({ behavior: 'smooth' }) }}>
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
            <button key={run.repo} onClick={() => { setUrl(`https://github.com/${run.repo}/pull/42`); setSelected(run.workflow === 'Laravel Doctor' ? 'doctor' : run.workflow === 'Issue Plan' ? 'plan' : 'review'); document.querySelector('#launcher')?.scrollIntoView({ behavior: 'smooth' }) }}>
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

function Running({ activeWorkflow, repo, step }) {
  const progress = Math.min(100, Math.round((step / executionSteps.length) * 100))
  return (
    <main className="running-page">
      <div className="run-heading">
        <div className="running-pulse"><Bot size={22} /></div>
        <div><div className="eyebrow">Workflow in progress</div><h1>Analyzing <em>{repo}</em></h1><p>{activeWorkflow.title} · This usually takes less than a minute</p></div>
      </div>
      <div className="progress-card">
        <div className="progress-head"><span>AI analysis</span><strong>{progress}%</strong></div>
        <div className="progress-track"><span style={{ width: `${progress}%` }} /></div>
        <div className="timeline">
          {executionSteps.map(([title, detail], index) => {
            const complete = index < step
            const current = index === step
            return (
              <div className={`timeline-row ${complete ? 'complete' : ''} ${current ? 'current' : ''}`} key={title}>
                <div className="status-icon">{complete ? <Check size={16} /> : current ? <LoaderCircle size={16} className="spin" /> : <span />}</div>
                <div><strong>{title}</strong><p>{complete || current ? detail : 'Waiting…'}</p></div>
                {complete && <span className="done-label">Done</span>}
              </div>
            )
          })}
        </div>
      </div>
      <p className="running-note"><ShieldCheck size={16} /> Your code is read-only and never stored after analysis.</p>
    </main>
  )
}

function Report({ workflow, repo, copied, setCopied, reset }) {
  const copy = async () => {
    await navigator.clipboard?.writeText(window.location.href + 'runs/a1f9c2')
    setCopied(true)
    setTimeout(() => setCopied(false), 1800)
  }
  return (
    <main className="report-page">
      <div className="report-topline"><button className="back-button" onClick={reset}>← New launch</button><div className="share-actions"><button onClick={copy}>{copied ? <Check size={16} /> : <Copy size={16} />}{copied ? 'Copied' : 'Copy link'}</button><button className="primary-share">Share report <ArrowRight size={16} /></button></div></div>
      <section className="report-hero">
        <div className="report-status"><CheckCircle2 size={16} /> Analysis complete</div>
        <h1>{workflow.title}</h1>
        <div className="repo-name"><GitFork size={18} /> {repo} <span>/ pull / 42</span></div>
        <div className="report-stats">
          <div><span>Risk level</span><strong className="risk"><CircleDot size={15} /> Medium</strong></div>
          <div><span>Findings</span><strong>3</strong></div>
          <div><span>Files analyzed</span><strong>12</strong></div>
          <div><span>Processing time</span><strong>34.2s</strong></div>
          <div><span>AI usage</span><strong>$0.08 · Sonnet</strong></div>
        </div>
      </section>

      <div className="report-layout">
        <aside>
          <span>On this page</span>
          <a href="#summary" className="active">Executive summary</a>
          <a href="#findings">Key findings <b>3</b></a>
          <a href="#checklist">Verification checklist</a>
          <div className="ai-card"><Sparkles size={18} /><strong>AI-generated report</strong><p>Always verify critical findings before merging.</p></div>
        </aside>
        <article className="report-content">
          <section id="summary">
            <div className="content-heading"><span>01</span><h2>Executive summary</h2></div>
            <div className="summary-box"><p>This pull request introduces useful filtering and organization features, but contains <strong>one authorization vulnerability that should be fixed before merging.</strong> The implementation is otherwise clean and follows the project’s established Laravel conventions.</p></div>
          </section>
          <section id="findings">
            <div className="content-heading"><span>02</span><h2>Key findings</h2><b>3 findings</b></div>
            <div className="findings-list">
              {findings.map((finding, index) => (
                <div className="finding" key={finding.title}>
                  <div className="finding-header"><span className={`severity ${finding.severity}`}>{finding.severity}</span><span className="finding-number">0{index + 1}</span></div>
                  <h3>{finding.title}</h3>
                  <code><Code2 size={14} /> {finding.file}</code>
                  <p>{finding.body}</p>
                  <div className="suggestion"><strong><Sparkles size={14} /> Suggested fix</strong><p>{finding.fix}</p></div>
                </div>
              ))}
            </div>
          </section>
          <section id="checklist">
            <div className="content-heading"><span>03</span><h2>Verification checklist</h2></div>
            <div className="checklist">
              {['Add authorization policy check before deleting tools', 'Replace usage counter update with atomic increment', 'Add feature tests for combined filters', 'Run the full test suite before merge'].map((item, index) => <label key={item}><input type="checkbox" defaultChecked={index === 3} /><span>{item}</span></label>)}
            </div>
          </section>
        </article>
      </div>
    </main>
  )
}

createRoot(document.getElementById('root')).render(<App />)
