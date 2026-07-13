import config from "./ai-launcher-config.json";

export interface AiLauncherTool {
    name: string;
    description: string;
    aliases: string[];
    providerId: string;
}

export interface LauncherTemplateMeta {
    slug: string;
    template: string;
    shortLabel: string;
    description: string;
}

export const aiLauncherTools = config.tools as AiLauncherTool[];
export const launcherTemplateMeta = config.launcherTemplates as LauncherTemplateMeta[];

export const aiLauncherToolsByName: Record<string, AiLauncherTool> = Object.fromEntries(
    aiLauncherTools.map((tool) => [tool.name, tool]),
);

export const launcherTemplateBySlug: Record<string, LauncherTemplateMeta> = Object.fromEntries(
    launcherTemplateMeta.map((meta) => [meta.slug, meta]),
);

export const defaultAiToolName = "codex";

export function resolveProviderIdForTool(toolName: string): string {
    return aiLauncherToolsByName[toolName]?.providerId ?? "openai";
}