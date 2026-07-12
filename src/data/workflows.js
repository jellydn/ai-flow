import {
  BookOpen,
  GitPullRequest,
  ListTodo,
  Newspaper,
  ShieldCheck,
  Stethoscope,
} from 'lucide-react'

export const workflows = [
  {
    id: 'review',
    slug: 'review-pr',
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
    slug: 'plan-issue',
    title: 'Plan GitHub issue',
    description: 'Turn an issue into a scoped, actionable implementation plan.',
    icon: ListTodo,
    tone: 'blue',
    time: '~30 sec',
    accepts: 'Issues',
  },
  {
    id: 'explain',
    slug: 'explain-repository',
    title: 'Explain repository',
    description: 'Understand architecture, key modules, and how everything fits.',
    icon: BookOpen,
    tone: 'purple',
    time: '~55 sec',
    accepts: 'Repositories',
  },
  {
    id: 'doctor',
    slug: 'laravel-doctor',
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
    slug: null,
    title: 'Write release notes',
    description: 'Turn a pull request or commit range into clear user-facing notes.',
    icon: Newspaper,
    tone: 'blue',
    time: '~25 sec',
    accepts: 'PRs or commits',
  },
  {
    id: 'security',
    slug: null,
    title: 'Security scan',
    description: 'Run a focused pass for auth, input, and data exposure risks.',
    icon: ShieldCheck,
    tone: 'purple',
    time: '~50 sec',
    accepts: 'Pull requests',
  },
]

export const workflowById = Object.fromEntries(workflows.map((w) => [w.id, w]))

export const recentRuns = [
  { repo: 'jellydn/my-ai-tools', run: 'Pull request #42', workflow: 'PR Review', risk: 'Medium', findings: 5, time: '34s' },
  { repo: 'laravel/framework', run: 'Repository', workflow: 'Laravel Doctor', risk: 'Low', findings: 3, time: '52s' },
  { repo: 'calcom/cal.com', run: 'Issue #20418', workflow: 'Issue Plan', risk: '—', findings: 8, time: '29s' },
]

export const demoExecutionSteps = [
  ['Reading GitHub metadata', 'Pull request #42 · 12 files changed'],
  ['Loading source context', '2,840 lines analyzed'],
  ['Running AI analysis', 'Reviewing logic, security, and test coverage'],
  ['Validating response', 'Checking findings and citations'],
  ['Generating report', 'Formatting your shareable result'],
]

export const demoFindings = [
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