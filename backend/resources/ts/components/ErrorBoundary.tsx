import type { ReactNode } from "react";
import React from "react";

interface Props {
    children: ReactNode;
}

interface State {
    error: Error | null;
}

export class ErrorBoundary extends React.Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    render() {
        if (this.state.error) {
            return (
                <div className="error-fallback" role="alert">
                    <h1>Something went wrong</h1>
                    <p>{this.state.error.message}</p>
                    <button type="button" onClick={() => window.location.reload()}>
                        Reload
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}
