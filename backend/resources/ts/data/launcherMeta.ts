import type { LucideIcon } from "lucide-react";
import {
    BookOpen,
    GitPullRequest,
    ListTodo,
    Newspaper,
    ShieldCheck,
    Stethoscope,
} from "lucide-react";
import type { Launcher } from "../types/api.ts";

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

export function quickLabel(slug: string, title: string): string {
    switch (slug) {
        case "review-pr":
            return "Review PR";
        case "plan-issue":
            return "Plan fix";
        case "explain-repository":
            return "Explain";
        case "laravel-doctor":
            return "Laravel doctor";
        default:
            return title;
    }
}
