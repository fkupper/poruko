import { useEffect } from 'react';

import { getModelContext } from './getModelContext';
import { registerPorukoTools } from './registerPorukoTools';

export function useWebMcpTools(activeLedgerId: number | null): void {
    useEffect(() => {
        const modelContext = getModelContext();
        if (!modelContext || activeLedgerId === null) {
            return;
        }

        const controller = new AbortController();
        registerPorukoTools(modelContext, controller.signal);

        return () => controller.abort();
    }, [activeLedgerId]);
}
