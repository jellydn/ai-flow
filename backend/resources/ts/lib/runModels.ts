import type { RunProviderId } from "../services/run.ts";

export type ProviderCatalogEntry = {
    id: RunProviderId;
    name: string;
    models: string[];
};

export function pickModelForProvider(
    providerId: RunProviderId,
    catalog: ProviderCatalogEntry[],
    current: string,
    credentialDefault?: string | null,
): string {
    const entry = catalog.find((p) => p.id === providerId);
    const models = entry?.models ?? [];
    if (models.length === 0) {
        return current;
    }
    if (credentialDefault) {
        return credentialDefault;
    }
    if (current && models.includes(current)) {
        return current;
    }

    return models[0];
}
