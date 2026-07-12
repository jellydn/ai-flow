import { useCallback, useEffect, useReducer, useRef } from "react";
import type { Run } from "../types/api.ts";
import { fetchRun } from "../services/run.ts";

type PathState = {
    runId: string | null;
    run: Run | null;
    loading: boolean;
    error: string | null;
    ready: boolean;
};

const initialPathState: PathState = {
    runId: null,
    run: null,
    loading: false,
    error: null,
    ready: false,
};

type PathAction =
    | { type: "not-run-path" }
    | { type: "begin"; id: string }
    | { type: "success"; id: string; run: Run }
    | { type: "failure"; id: string; message: string }
    | { type: "settle"; id: string };

function pathReducer(state: PathState, action: PathAction): PathState {
    switch (action.type) {
        case "not-run-path":
            return { runId: null, run: null, loading: false, error: null, ready: true };
        case "begin":
            return { runId: action.id, run: null, loading: true, error: null, ready: false };
        case "success":
            return { ...state, run: action.run, error: null };
        case "failure":
            return { ...state, run: null, error: action.message };
        case "settle":
            return { ...state, loading: false, ready: true };
        default:
            return state;
    }
}

export function useRunFromPath() {
    const [state, dispatch] = useReducer(pathReducer, initialPathState);
    const currentIdRef = useRef<string | null>(null);

    const navigate = useCallback((pathname: string) => {
        const match = pathname.match(/^\/?runs\/([0-9a-f-]+)\/?$/i);

        if (!match) {
            currentIdRef.current = null;
            dispatch({ type: "not-run-path" });
            return;
        }

        const id = match[1];
        currentIdRef.current = id;
        dispatch({ type: "begin", id });

        fetchRun(id)
            .then((snapshot) => {
                if (currentIdRef.current !== id) {
                    return;
                }
                dispatch({ type: "success", id, run: snapshot });
            })
            .catch((e) => {
                if (currentIdRef.current !== id) {
                    return;
                }
                dispatch({
                    type: "failure",
                    id,
                    message: e instanceof Error ? e.message : "Could not load this report.",
                });
            })
            .finally(() => {
                if (currentIdRef.current !== id) {
                    return;
                }
                dispatch({ type: "settle", id });
            });
    }, []);

    useEffect(() => {
        navigate(window.location.pathname);

        const handlePopState = () => navigate(window.location.pathname);
        window.addEventListener("popstate", handlePopState);
        return () => window.removeEventListener("popstate", handlePopState);
    }, [navigate]);

    return { ...state, navigate };
}
