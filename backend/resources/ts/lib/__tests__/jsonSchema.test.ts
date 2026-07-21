import { describe, expect, it } from "vitest";
import { isValidJsonObjectSchema } from "../jsonSchema.ts";

describe("isValidJsonObjectSchema", () => {
    it("returns true for a valid schema with a 'type' key (no properties)", () => {
        // Type-only schema isolates the `type` branch: a regression that
        // kept only the `properties` check would fail this test.
        expect(isValidJsonObjectSchema('{"type":"string"}')).toBe(true);
    });

    it("returns true for a valid schema with only a 'properties' key", () => {
        expect(isValidJsonObjectSchema('{"properties":{"summary":{"type":"string"}}}')).toBe(true);
    });

    it("returns false for a plain object without schema keys", () => {
        expect(isValidJsonObjectSchema('{"hello":"world"}')).toBe(false);
    });

    it("returns false for an empty object", () => {
        expect(isValidJsonObjectSchema("{}")).toBe(false);
    });

    it("returns false for a JSON array", () => {
        expect(isValidJsonObjectSchema("[1,2,3]")).toBe(false);
    });

    it("returns false for a JSON primitive", () => {
        expect(isValidJsonObjectSchema('"just a string"')).toBe(false);
        expect(isValidJsonObjectSchema("42")).toBe(false);
        expect(isValidJsonObjectSchema("true")).toBe(false);
        expect(isValidJsonObjectSchema("null")).toBe(false);
    });

    it("returns false for non-JSON input", () => {
        expect(isValidJsonObjectSchema("not json at all")).toBe(false);
        expect(isValidJsonObjectSchema("{broken")).toBe(false);
        expect(isValidJsonObjectSchema("")).toBe(false);
    });
});
