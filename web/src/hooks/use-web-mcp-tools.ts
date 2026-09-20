import * as React from 'react';
import axios from 'axios';

import { executeMcpTool, type McpToolDefinition } from '@/api/mcp';

export type WebMcpStatus = 'unsupported' | 'registering' | 'ready' | 'error';

interface WebMcpRegistrationState {
    status: WebMcpStatus;
    registeredCount: number;
    error: string | null;
}

function executionError(error: unknown): Error {
    if (axios.isAxiosError(error)) {
        const message = error.response?.data?.message;
        if (typeof message === 'string') {
            return new Error(message);
        }
    }

    return error instanceof Error ? error : new Error('The WebMCP action failed.');
}

export function useWebMcpTools(tools: McpToolDefinition[]): WebMcpRegistrationState {
    const [state, setState] = React.useState<WebMcpRegistrationState>(() => ({
        status: typeof document !== 'undefined' && document.modelContext
            ? 'registering'
            : 'unsupported',
        registeredCount: 0,
        error: null,
    }));

    React.useEffect(() => {
        const modelContext = document.modelContext;
        if (!modelContext) {
            return;
        }

        const controllers = tools.map(() => new AbortController());
        let disposed = false;

        Promise.all(tools.map((tool, index) => modelContext.registerTool({
            name: tool.name,
            title: tool.title,
            description: tool.description,
            inputSchema: tool.input_schema,
            annotations: {
                readOnlyHint: tool.annotations.readOnlyHint,
                untrustedContentHint: true,
            },
            execute: async (input) => {
                try {
                    const result = await executeMcpTool(tool.name, input);
                    return JSON.stringify(result);
                } catch (error) {
                    throw executionError(error);
                }
            },
        }, { signal: controllers[index].signal })))
            .then(() => {
                if (!disposed) {
                    setState({
                        status: 'ready',
                        registeredCount: tools.length,
                        error: null,
                    });
                }
            })
            .catch((error: unknown) => {
                if (!disposed) {
                    setState({
                        status: 'error',
                        registeredCount: 0,
                        error: executionError(error).message,
                    });
                }
            });

        return () => {
            disposed = true;
            controllers.forEach((controller) => controller.abort());
        };
    }, [tools]);

    return state;
}
