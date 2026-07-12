import { createRoot } from "react-dom/client";
import { App } from "./components/App.tsx";
import { ErrorBoundary } from "./components/ErrorBoundary.tsx";
import "../css/app.css";

createRoot(document.getElementById("root") as HTMLElement).render(
    <ErrorBoundary>
        <App />
    </ErrorBoundary>,
);
