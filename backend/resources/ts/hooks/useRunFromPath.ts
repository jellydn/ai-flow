import { useCallback, useEffect, useRef, useState } from 'react';
import type { Run } from '../types/api.ts';
import { fetchRun } from '../services/run.ts';

export function useRunFromPath() {
    const [runId, setRunId] = useState<string | null>(null);
    const [run, setRun] = useState<Run | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [ready, setReady] = useState(false);
    const currentIdRef = useRef<string | null>(null);

    const navigate = useCallback((pathname: string) => {
        const match = pathname.match(/^\/?runs\/([0-9a-f-]+)\/?$/i);

        if (!match) {
            currentIdRef.current = null;
            setRunId(null);
            setRun(null);
            setError(null);
            setLoading(false);
            setReady(true);
            return;
        }

        const id = match[1];
        currentIdRef.current = id;
        setRunId(id);
        setLoading(true);
        setError(null);
        setRun(null);

        fetchRun(id)
            .then((snapshot) => {
                if (currentIdRef.current !== id) {
                    return;
                }
                setRun(snapshot);
                setError(null);
            })
            .catch((e) => {
                if (currentIdRef.current !== id) {
                    return;
                }
                setRun(null);
                setError(e instanceof Error ? e.message : 'Could not load this report.');
            })
            .finally(() => {
                if (currentIdRef.current !== id) {
                    return;
                }
                setLoading(false);
                setReady(true);
            });
    }, []);

    useEffect(() => {
        navigate(window.location.pathname);

        const handlePopState = () => navigate(window.location.pathname);
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, [navigate]);

    return { runId, run, loading, error, ready, navigate };
}
