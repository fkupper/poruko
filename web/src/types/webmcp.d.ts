export {};

declare global {
    interface ModelContextTool {
        name: string;
        title?: string;
        description: string;
        inputSchema?: Record<string, unknown>;
        annotations?: {
            readOnlyHint?: boolean;
            untrustedContentHint?: boolean;
        };
        execute: (
            input: Record<string, unknown>,
            options: { signal: AbortSignal },
        ) => string | Promise<string>;
    }

    interface ModelContext {
        registerTool(
            tool: ModelContextTool,
            options?: { signal?: AbortSignal; exposedTo?: string[] },
        ): Promise<undefined>;
    }

    interface Document {
        modelContext?: ModelContext;
    }
}
