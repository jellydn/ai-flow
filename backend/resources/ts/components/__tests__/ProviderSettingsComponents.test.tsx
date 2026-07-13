import type { FormEvent } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { CredentialForm } from "../CredentialForm.tsx";
import { CredentialList } from "../CredentialList.tsx";
import { PrivacyNote } from "../PrivacyNote.tsx";

// ---------------------------------------------------------------------------
// CredentialForm
// ---------------------------------------------------------------------------
describe("CredentialForm", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    const providers = [
        { id: "openai", name: "OpenAI" },
        { id: "anthropic", name: "Anthropic" },
    ];

    it("renders provider select with options", () => {
        render(
            <CredentialForm
                provider="openai"
                setProvider={vi.fn()}
                label=""
                setLabel={vi.fn()}
                apiKey=""
                setApiKey={vi.fn()}
                submitting={false}
                error=""
                onSubmit={vi.fn()}
                providers={providers}
            />,
        );
        expect(screen.getByRole("combobox")).toBeInTheDocument();
        expect(screen.getByText("OpenAI")).toBeInTheDocument();
        expect(screen.getByText("Anthropic")).toBeInTheDocument();
    });

    it("calls setProvider on select change", async () => {
        const setProvider = vi.fn();
        render(
            <CredentialForm
                provider="openai"
                setProvider={setProvider}
                label=""
                setLabel={vi.fn()}
                apiKey=""
                setApiKey={vi.fn()}
                submitting={false}
                error=""
                onSubmit={vi.fn()}
                providers={providers}
            />,
        );

        await userEvent.setup().selectOptions(screen.getByRole("combobox"), "anthropic");
        expect(setProvider).toHaveBeenCalledWith("anthropic");
    });

    it("calls setLabel on input change", async () => {
        const setLabel = vi.fn();
        render(
            <CredentialForm
                provider="openai"
                setProvider={vi.fn()}
                label=""
                setLabel={setLabel}
                apiKey=""
                setApiKey={vi.fn()}
                submitting={false}
                error=""
                onSubmit={vi.fn()}
                providers={providers}
            />,
        );

        await userEvent.setup().type(screen.getByPlaceholderText(/Label/), "My Key");
        expect(setLabel).toHaveBeenCalled();
    });

    it("calls setApiKey on input change", async () => {
        const setApiKey = vi.fn();
        render(
            <CredentialForm
                provider="openai"
                setProvider={vi.fn()}
                label=""
                setLabel={vi.fn()}
                apiKey=""
                setApiKey={setApiKey}
                submitting={false}
                error=""
                onSubmit={vi.fn()}
                providers={providers}
            />,
        );

        await userEvent.setup().type(screen.getByPlaceholderText("API key"), "sk-123");
        expect(setApiKey).toHaveBeenCalled();
    });

    it("calls onSubmit when form is submitted", async () => {
        const onSubmit = vi.fn<(e: FormEvent) => void>((e) => e.preventDefault());
        render(
            <CredentialForm
                provider="openai"
                setProvider={vi.fn()}
                label="My Key"
                setLabel={vi.fn()}
                apiKey="sk-123"
                setApiKey={vi.fn()}
                submitting={false}
                error=""
                onSubmit={onSubmit}
                providers={providers}
            />,
        );

        await userEvent.setup().click(screen.getByRole("button", { name: "Save" }));
        expect(onSubmit).toHaveBeenCalled();
    });

    it("disables submit button and shows 'Saving…' when submitting", () => {
        render(
            <CredentialForm
                provider="openai"
                setProvider={vi.fn()}
                label=""
                setLabel={vi.fn()}
                apiKey=""
                setApiKey={vi.fn()}
                submitting={true}
                error=""
                onSubmit={vi.fn()}
                providers={providers}
            />,
        );
        const btn = screen.getByRole("button", { name: "Saving…" });
        expect(btn).toBeDisabled();
    });

    it("shows error message when error prop is set", () => {
        render(
            <CredentialForm
                provider="openai"
                setProvider={vi.fn()}
                label=""
                setLabel={vi.fn()}
                apiKey=""
                setApiKey={vi.fn()}
                submitting={false}
                error="Label and API key are required."
                onSubmit={vi.fn()}
                providers={providers}
            />,
        );
        expect(screen.getByText("Label and API key are required.")).toBeInTheDocument();
    });
});

// ---------------------------------------------------------------------------
// CredentialList
// ---------------------------------------------------------------------------
describe("CredentialList", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it("shows loading state when loading is true", () => {
        render(
            <CredentialList
                loading={true}
                credentials={[]}
                verifyResults={{}}
                onVerify={vi.fn()}
                onDelete={vi.fn()}
            />,
        );
        expect(screen.getByText("Loading credentials…")).toBeInTheDocument();
    });

    it("shows empty state when no credentials and not loading", () => {
        render(
            <CredentialList
                loading={false}
                credentials={[]}
                verifyResults={{}}
                onVerify={vi.fn()}
                onDelete={vi.fn()}
            />,
        );
        expect(screen.getByText(/No API keys saved yet/)).toBeInTheDocument();
    });

    it("renders credential items with label and provider", () => {
        render(
            <CredentialList
                loading={false}
                credentials={[
                    {
                        id: "c1",
                        provider: "openai",
                        label: "Personal",
                        masked_key: "sk-...abc",
                        default_model: null,
                        is_default: false,
                        last_verified_at: null,
                        last_used_at: null,
                        created_at: "2026-01-01T00:00:00Z",
                        updated_at: "2026-01-01T00:00:00Z",
                    },
                    {
                        id: "c2",
                        provider: "anthropic",
                        label: "Work",
                        masked_key: "sk-...xyz",
                        default_model: null,
                        is_default: true,
                        last_verified_at: null,
                        last_used_at: null,
                        created_at: "2026-01-01T00:00:00Z",
                        updated_at: "2026-01-01T00:00:00Z",
                    },
                ]}
                verifyResults={{}}
                onVerify={vi.fn()}
                onDelete={vi.fn()}
            />,
        );
        expect(screen.getByText("Personal")).toBeInTheDocument();
        expect(screen.getByText("Work")).toBeInTheDocument();
        expect(screen.getByText("openai")).toBeInTheDocument();
        expect(screen.getByText("anthropic")).toBeInTheDocument();
        expect(screen.getByText("default")).toBeInTheDocument();
        expect(screen.getByText("sk-...abc")).toBeInTheDocument();
    });

    it("calls onVerify when Verify button is clicked", async () => {
        const onVerify = vi.fn();
        render(
            <CredentialList
                loading={false}
                credentials={[
                    {
                        id: "c1",
                        provider: "openai",
                        label: "Personal",
                        masked_key: "sk-...abc",
                        default_model: null,
                        is_default: false,
                        last_verified_at: null,
                        last_used_at: null,
                        created_at: "2026-01-01T00:00:00Z",
                        updated_at: "2026-01-01T00:00:00Z",
                    },
                ]}
                verifyResults={{}}
                onVerify={onVerify}
                onDelete={vi.fn()}
            />,
        );

        await userEvent.setup().click(screen.getByRole("button", { name: "Verify" }));
        expect(onVerify).toHaveBeenCalledWith("c1");
    });

    it("calls onDelete when Delete button is clicked", async () => {
        const onDelete = vi.fn();
        render(
            <CredentialList
                loading={false}
                credentials={[
                    {
                        id: "c1",
                        provider: "openai",
                        label: "Personal",
                        masked_key: "sk-...abc",
                        default_model: null,
                        is_default: false,
                        last_verified_at: null,
                        last_used_at: null,
                        created_at: "2026-01-01T00:00:00Z",
                        updated_at: "2026-01-01T00:00:00Z",
                    },
                ]}
                verifyResults={{}}
                onVerify={vi.fn()}
                onDelete={onDelete}
            />,
        );

        await userEvent.setup().click(screen.getByRole("button", { name: "Delete" }));
        expect(onDelete).toHaveBeenCalledWith("c1");
    });

    it("shows verify result when present", () => {
        render(
            <CredentialList
                loading={false}
                credentials={[
                    {
                        id: "c1",
                        provider: "openai",
                        label: "Personal",
                        masked_key: "sk-...abc",
                        default_model: null,
                        is_default: false,
                        last_verified_at: null,
                        last_used_at: null,
                        created_at: "2026-01-01T00:00:00Z",
                        updated_at: "2026-01-01T00:00:00Z",
                    },
                ]}
                verifyResults={{ c1: "Connection successful." }}
                onVerify={vi.fn()}
                onDelete={vi.fn()}
            />,
        );
        expect(screen.getByText("Connection successful.")).toBeInTheDocument();
    });
});

// ---------------------------------------------------------------------------
// PrivacyNote
// ---------------------------------------------------------------------------
describe("PrivacyNote", () => {
    it("renders the encryption privacy text", () => {
        render(<PrivacyNote />);
        expect(screen.getByText(/Your API keys are encrypted before storage/)).toBeInTheDocument();
        expect(screen.getByText(/decrypted only when sending a request/)).toBeInTheDocument();
    });
});
