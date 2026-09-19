export type ModelContextLike = {
    registerTool: (def: {
        name: string;
        description: string;
        inputSchema: Record<string, unknown>;
        execute: (input: Record<string, unknown>) => Promise<unknown> | unknown;
        signal?: AbortSignal;
        readOnlyHint?: boolean;
    }) => void;
};

export function getModelContext(): ModelContextLike | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const documentContext = (document as Document & { modelContext?: ModelContextLike }).modelContext;
    const navigatorContext =
        typeof navigator !== 'undefined'
            ? (navigator as Navigator & { modelContext?: ModelContextLike }).modelContext
            : undefined;
    const modelContext = documentContext ?? navigatorContext;

    return modelContext && typeof modelContext.registerTool === 'function' ? modelContext : null;
}
