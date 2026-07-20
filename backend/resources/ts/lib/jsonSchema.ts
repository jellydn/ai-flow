/**
 * Light-weight structural check for a JSON Schema object.
 *
 * Returns true only when `raw` parses as JSON, is a plain object (not an
 * array or primitive), and contains at least one of the recognised schema
 * anchor keys (`type` or `properties`). This is intentionally a *shape* check,
 * not full JSON Schema validation — it exists to give the Custom Launchers
 * form fast, offline feedback that the user's `output_schema` looks like a
 * schema rather than arbitrary JSON.
 *
 * @param raw JSON string to validate.
 * @returns `true` when `raw` is a JSON object containing `type` or `properties`.
 */
export const isValidJsonObjectSchema = (raw: string): boolean => {
    try {
        const parsed = JSON.parse(raw);
        return (
            typeof parsed === "object" &&
            parsed !== null &&
            !Array.isArray(parsed) &&
            ("type" in parsed || "properties" in parsed)
        );
    } catch {
        return false;
    }
};
