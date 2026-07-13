import type { LucideIcon } from "lucide-react";
import { launcherTemplateBySlug } from "./aiLauncherConfig.ts";
import {
    BookOpen,
    GitPullRequest,
    ListTodo,
    Newspaper,
    ShieldCheck,
    Stethoscope,
} from "lucide-react";
import type { Finding, Launcher, ProgressStep } from "../types/api.ts";

export interface LauncherMeta {
    slug: string | null;
    title: string;
    description: string;
    icon: LucideIcon;
    tone: "orange" | "blue" | "purple" | "green";
    time: string;
    accepts: string;
    popular?: boolean;
    badge?: string;
}

export const launcherMeta: LauncherMeta[] = [
    {
        slug: "review-pr",
        title: "Review pull request",
        description: "Find bugs, security risks, and regressions before they ship.",
        icon: GitPullRequest,
        tone: "orange",
        time: "~45 sec",
        accepts: "Pull requests",
        popular: true,
    },
    {
        slug: "plan-issue",
        title: "Plan GitHub issue",
        description: "Turn an issue into a scoped, actionable implementation plan.",
        icon: ListTodo,
        tone: "blue",
        time: "~30 sec",
        accepts: "Issues",
    },
    {
        slug: "explain-repository",
        title: "Explain repository",
        description: "Understand architecture, key modules, and how everything fits.",
        icon: BookOpen,
        tone: "purple",
        time: "~55 sec",
        accepts: "Repositories",
    },
    {
        slug: "laravel-doctor",
        title: "Laravel project doctor",
        description: "Audit Laravel conventions, performance, and project health.",
        icon: Stethoscope,
        tone: "green",
        time: "~60 sec",
        accepts: "Repositories",
        badge: "Laravel",
    },
    {
        slug: null,
        title: "Write release notes",
        description: "Turn a pull request or commit range into clear user-facing notes.",
        icon: Newspaper,
        tone: "blue",
        time: "~25 sec",
        accepts: "PRs or commits",
    },
    {
        slug: null,
        title: "Security scan",
        description: "Run a focused pass for auth, input, and data exposure risks.",
        icon: ShieldCheck,
        tone: "purple",
        time: "~50 sec",
        accepts: "Pull requests",
    },
];

export const launcherMetaBySlug: Record<string, LauncherMeta> = Object.fromEntries(
    launcherMeta.filter((meta) => meta.slug !== null).map((meta) => [meta.slug as string, meta]),
);

export const staticLaunchers: Launcher[] = launcherMeta
    .filter((meta) => meta.slug !== null)
    .map((meta) => ({
        id: meta.slug as string,
        slug: meta.slug as string,
        name: meta.title,
        description: meta.description,
        input_type: "",
    }));

export interface RecentRun {
    repo: string;
    run: string;
    workflow: string;
    risk: string;
    findings: number;
    time: string;
}

export const recentRuns: RecentRun[] = [
    {
        repo: "jellydn/my-ai-tools",
        run: "Pull request #42",
        workflow: "PR Review",
        risk: "Medium",
        findings: 5,
        time: "34s",
    },
    {
        repo: "laravel/framework",
        run: "Repository",
        workflow: "Laravel Doctor",
        risk: "Low",
        findings: 3,
        time: "52s",
    },
    {
        repo: "calcom/cal.com",
        run: "Issue #20418",
        workflow: "Issue Plan",
        risk: "—",
        findings: 8,
        time: "29s",
    },
];

export function workflowTitleToSlug(title: string): string {
    switch (title) {
        case "PR Review":
            return "review-pr";
        case "Laravel Doctor":
            return "laravel-doctor";
        case "Issue Plan":
            return "plan-issue";
        default:
            return "review-pr";
    }
}

export function quickLabel(slug: string, title: string): string {
    return launcherTemplateBySlug[slug]?.shortLabel ?? title;
}

export const demoSteps: ProgressStep[] = [
    { title: "Reading GitHub metadata", detail: "Pull request #42 · 12 files changed" },
    { title: "Loading source context", detail: "2,840 lines analyzed" },
    { title: "Running AI analysis", detail: "Reviewing logic, security, and test coverage" },
    { title: "Validating response", detail: "Checking findings and citations" },
    { title: "Generating report", detail: "Formatting your shareable result" },
];

export const demoFindings: Finding[] = [
    {
        severity: "high",
        title: "Missing authorization check on tool deletion",
        description:
            "The destroy action loads a tool by ID but does not verify that it belongs to the authenticated user. A user could delete another user’s tool by changing the route parameter.",
        recommendation:
            "Add a policy check with $this->authorize('delete', $tool) before deletion.",
    },
    {
        severity: "medium",
        title: "Race condition when updating usage counters",
        description:
            "The read-modify-write sequence is not atomic. Concurrent requests can overwrite each other and undercount usage.",
        recommendation: "Use Eloquent’s atomic increment() method inside the existing transaction.",
    },
    {
        severity: "low",
        title: "New filtering behavior has no test coverage",
        description:
            "The new category and status filters are user-facing but are not covered by feature tests.",
        recommendation:
            "Add cases for combined filters, empty results, and invalid category values.",
    },
];
