import '@testing-library/jest-dom/vitest';

// RTL auto-cleans after each test under Vitest.
// Do not register afterEach/beforeEach in setupFiles — Vitest 4 can throw
// "failed to find the current suite" when suite hooks run outside a test context.

Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false,
    }),
});
