import { launcherMetaBySlug } from "../data/launcherMeta.ts";
import type { Run } from "../types/api.ts";

export type ViewState =
    | { type: "home" }
    | { type: "demo-running"; step: number }
    | { type: "live-running"; runId: string; run: Run | null }
    | { type: "report"; run: Run | null }
    | { type: "failed"; run: Run };

export type AppUiState = {
    selected: string;
    url: string;
    view: ViewState;
    error: string;
};

export const initialAppUiState: AppUiState = {
    selected: "review-pr",
    url: "",
    view: { type: "home" },
    error: "",
};

export function launcherSlugForRun(run: Run): string {
    return run.launcher ? (launcherMetaBySlug[run.launcher]?.slug ?? run.launcher) : "review-pr";
}

export function viewStateForRun(run: Run): ViewState {
    if (run.status === "completed") {
        return { type: "report", run };
    }
    if (run.status === "failed") {
        return { type: "failed", run };
    }

    return { type: "live-running", runId: run.id, run };
}

export function uiStateFromRun(prev: AppUiState, run: Run): AppUiState {
    return {
        ...prev,
        selected: launcherSlugForRun(run),
        url: run.input?.source_url ?? "",
        view: viewStateForRun(run),
        error: "",
    };
}
