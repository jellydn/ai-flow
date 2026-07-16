import { describe, expect, it } from "vitest";
import { pickModelForProvider } from "../runModels.ts";

const catalog = [
    { id: "openai" as const, name: "OpenAI", models: ["gpt-4o-mini", "gpt-4o"] },
    { id: "anthropic" as const, name: "Anthropic", models: ["claude-sonnet-4-20250514"] },
];

describe("pickModelForProvider", () => {
    it("prefers credential default when valid", () => {
        expect(pickModelForProvider("openai", catalog, "gpt-4o-mini", "gpt-4o")).toBe("gpt-4o");
    });

    it("preserves a custom credential default", () => {
        expect(pickModelForProvider("openai", catalog, "gpt-4o-mini", "gpt-5.1")).toBe("gpt-5.1");
    });

    it("keeps current model when still valid", () => {
        expect(pickModelForProvider("openai", catalog, "gpt-4o", null)).toBe("gpt-4o");
    });

    it("falls back to first model when switching provider", () => {
        expect(pickModelForProvider("anthropic", catalog, "gpt-4o-mini", null)).toBe(
            "claude-sonnet-4-20250514",
        );
    });
});
