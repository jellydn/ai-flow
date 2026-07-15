import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { LaunchArea } from "../LaunchArea.tsx";
import type { ProviderCredential } from "../../services/auth.ts";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
const baseProps = {
    provider: "openai" as const,
    setProvider: vi.fn(),
    apiKey: "",
    setApiKey: vi.fn(),
    launch: vi.fn(),
    isLaunching: false,
};

function makeCredential(overrides: Partial<ProviderCredential> = {}): ProviderCredential {
    return {
        id: "cred-1",
        provider: "openai",
        label: "My OpenAI key",
        masked_key: "sk-...abc",
        default_model: null,
        is_default: false,
        last_verified_at: null,
        last_used_at: null,
        created_at: "2026-01-01T00:00:00Z",
        updated_at: "2026-01-01T00:00:00Z",
        ...overrides,
    };
}

// ---------------------------------------------------------------------------
// LaunchArea — saved-credential picker
// ---------------------------------------------------------------------------
describe("LaunchArea — saved-credential picker", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it("does not render saved-key dropdown when no credentials exist", () => {
        render(<LaunchArea {...baseProps} credentials={[]} />);
        expect(screen.queryByLabelText("Saved API credential")).not.toBeInTheDocument();
    });

    it("renders saved-key dropdown when credentials exist", () => {
        const creds = [makeCredential()];
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );
        expect(screen.getByLabelText("Saved API credential")).toBeInTheDocument();
        expect(screen.getByText("Use one-time key / server key")).toBeInTheDocument();
        expect(screen.getByText(/My OpenAI key \(openai\)/)).toBeInTheDocument();
        expect(screen.getByText(/sk-\.\.\.abc/)).toBeInTheDocument();
    });

    it("renders one-time key/provider inputs by default (no saved credential selected)", () => {
        const creds = [makeCredential()];
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );
        expect(screen.getByLabelText("AI provider")).toBeInTheDocument();
        expect(screen.getByPlaceholderText(/Leave blank/)).toBeInTheDocument();
    });

    it("hides one-time provider and API key inputs when a saved credential is selected", () => {
        const creds = [makeCredential()];
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId="cred-1"
                setSelectedCredentialId={vi.fn()}
            />,
        );
        expect(screen.getByLabelText("Saved API credential")).toBeInTheDocument();
        expect(screen.queryByLabelText("AI provider")).not.toBeInTheDocument();
        expect(screen.queryByPlaceholderText(/Leave blank/)).not.toBeInTheDocument();
    });

    it("calls setSelectedCredentialId when dropdown changes", async () => {
        const creds = [makeCredential()];
        const setSelectedCredentialId = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={setSelectedCredentialId}
            />,
        );

        await userEvent
            .setup()
            .selectOptions(screen.getByLabelText("Saved API credential"), "cred-1");
        expect(setSelectedCredentialId).toHaveBeenCalledWith("cred-1");
    });

    it("calls setSelectedCredentialId with null when 'Use one-time key' is re-selected", async () => {
        const creds = [makeCredential()];
        const setSelectedCredentialId = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId="cred-1"
                setSelectedCredentialId={setSelectedCredentialId}
            />,
        );

        await userEvent.setup().selectOptions(screen.getByLabelText("Saved API credential"), "");
        expect(setSelectedCredentialId).toHaveBeenCalledWith(null);
    });

    it("auto-selects provider to match the credential when a saved key is chosen", async () => {
        const creds = [makeCredential({ id: "cred-or", provider: "openrouter" })];
        const setProvider = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                setProvider={setProvider}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );

        await userEvent
            .setup()
            .selectOptions(screen.getByLabelText("Saved API credential"), "cred-or");
        expect(setProvider).toHaveBeenCalledWith("openrouter");
    });

    it("auto-selects provider for anthropic credentials", async () => {
        const creds = [makeCredential({ id: "cred-an", provider: "anthropic" })];
        const setProvider = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                setProvider={setProvider}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );

        await userEvent
            .setup()
            .selectOptions(screen.getByLabelText("Saved API credential"), "cred-an");
        expect(setProvider).toHaveBeenCalledWith("anthropic");
    });

    it("auto-selects provider for gemini credentials", async () => {
        const creds = [makeCredential({ id: "cred-gm", provider: "gemini" })];
        const setProvider = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                setProvider={setProvider}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );

        await userEvent
            .setup()
            .selectOptions(screen.getByLabelText("Saved API credential"), "cred-gm");
        expect(setProvider).toHaveBeenCalledWith("gemini");
    });

    it("does not call setProvider when credential provider is unrecognized", async () => {
        const creds = [makeCredential({ id: "cred-x", provider: "unknown-provider" })];
        const setProvider = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                setProvider={setProvider}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );

        await userEvent
            .setup()
            .selectOptions(screen.getByLabelText("Saved API credential"), "cred-x");
        expect(setProvider).not.toHaveBeenCalled();
    });

    it("does not call setProvider when switching back to one-time key", async () => {
        const creds = [makeCredential()];
        const setProvider = vi.fn();
        render(
            <LaunchArea
                {...baseProps}
                setProvider={setProvider}
                credentials={creds}
                selectedCredentialId="cred-1"
                setSelectedCredentialId={vi.fn()}
            />,
        );

        await userEvent.setup().selectOptions(screen.getByLabelText("Saved API credential"), "");
        expect(setProvider).not.toHaveBeenCalled();
    });

    it("renders saved-credential privacy message when a saved key is selected", () => {
        const creds = [makeCredential()];
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId="cred-1"
                setSelectedCredentialId={vi.fn()}
            />,
        );
        expect(screen.getByText(/Using your saved encrypted API key/)).toBeInTheDocument();
        expect(screen.getByText(/decrypted only for this execution/)).toBeInTheDocument();
    });

    it("renders one-time key privacy message when no saved key is selected", () => {
        const creds = [makeCredential()];
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );
        expect(screen.getByText(/One-time or server key for this run only/)).toBeInTheDocument();
    });

    it("renders one-time key privacy message when no credentials exist", () => {
        render(<LaunchArea {...baseProps} credentials={[]} />);
        expect(
            screen.getByText(/Use your own API key to execute this workflow/),
        ).toBeInTheDocument();
    });

    it("renders multiple credential options in the dropdown", () => {
        const creds = [
            makeCredential({ id: "c1", label: "Personal", provider: "openai" }),
            makeCredential({ id: "c2", label: "Work", provider: "anthropic" }),
        ];
        render(
            <LaunchArea
                {...baseProps}
                credentials={creds}
                selectedCredentialId={null}
                setSelectedCredentialId={vi.fn()}
            />,
        );
        const select = screen.getByLabelText("Saved API credential") as HTMLSelectElement;
        expect(select.options).toHaveLength(3); // placeholder + 2 credentials
        expect(screen.getByText(/Personal/)).toBeInTheDocument();
        expect(screen.getByText(/Work/)).toBeInTheDocument();
    });
});
