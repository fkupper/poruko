import { describe, expect, it } from 'vitest';

import { getModelContext } from './getModelContext';

describe('getModelContext', () => {
    it('returns null when no model context exists', () => {
        expect(getModelContext()).toBeNull();
    });

    it('prefers document.modelContext', () => {
        const registerTool = () => {};
        Object.defineProperty(document, 'modelContext', {
            configurable: true,
            value: { registerTool },
        });

        expect(getModelContext()?.registerTool).toBe(registerTool);
        delete (document as Document & { modelContext?: unknown }).modelContext;
    });

    it('falls back to navigator.modelContext', () => {
        const registerTool = () => {};
        Object.defineProperty(navigator, 'modelContext', {
            configurable: true,
            value: { registerTool },
        });

        expect(getModelContext()?.registerTool).toBe(registerTool);
        delete (navigator as Navigator & { modelContext?: unknown }).modelContext;
    });

    it('rejects objects without registerTool', () => {
        Object.defineProperty(document, 'modelContext', {
            configurable: true,
            value: { nope: true },
        });

        expect(getModelContext()).toBeNull();
        delete (document as Document & { modelContext?: unknown }).modelContext;
    });
});
