import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Report } from "../Report.tsx";

const noop = () => {};

describe("Report", () => {
    it("renders markdown in summary and findings", () => {
        render(
            <Report
                launcherName="Review PR"
                repo="owner/repo"
                copied={false}
                setCopied={noop}
                reset={noop}
                runId="run-1"
                providerLabel="OpenAI"
                model="gpt-4o-mini"
                result={{
                    summary: "Use **bold** and `inline code` in the summary.",
                    risk: "medium",
                    findings: [
                        {
                            severity: "high",
                            title: "Auth gap",
                            description: "Details with a list:\n\n- first item\n- second item",
                            recommendation: "Fix with `Policy::check()` before delete.",
                        },
                    ],
                    verification_steps: ["Run `php artisan test`"],
                }}
            />,
        );

        expect(screen.getByText("bold")).toBeInTheDocument();
        expect(screen.getByText("inline code")).toBeInTheDocument();
        expect(screen.getByText("first item")).toBeInTheDocument();
        expect(screen.getByText("Policy::check()")).toBeInTheDocument();
        expect(screen.getByText("php artisan test")).toBeInTheDocument();
        expect(screen.getByText(/Generated with/)).toBeInTheDocument();
        expect(screen.getAllByText(/OpenAI · gpt-4o-mini/).length).toBeGreaterThanOrEqual(1);
    });
});
