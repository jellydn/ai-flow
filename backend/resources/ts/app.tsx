import * as Sentry from "@sentry/react";
import { createRoot } from "react-dom/client";
import { App } from "./components/App.tsx";
import { ErrorBoundary } from "./components/ErrorBoundary.tsx";
import "../css/app.css";

Sentry.init({
    dsn: import.meta.env.VITE_SENTRY_DSN,
    environment: import.meta.env.PROD ? "production" : "development",
    tracesSampleRate: import.meta.env.PROD ? 0.1 : 0,
});

createRoot(document.getElementById("root") as HTMLElement).render(
    <ErrorBoundary>
        <App />
    </ErrorBoundary>,
);
