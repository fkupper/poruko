import { describe, expect, it } from 'vitest';

import { allocateEqualCents, allocateProportionalCents } from './split';

describe('allocateEqualCents', () => {
    it('puts remainder on the last participant', () => {
        const result = allocateEqualCents(100, [1, 2, 3]);

        expect(result).toEqual({ 1: 33, 2: 33, 3: 34 });
        expect(Object.values(result).reduce((a, b) => a + b, 0)).toBe(100);
    });

    it('returns empty map for no participants', () => {
        expect(allocateEqualCents(100, [])).toEqual({});
    });

    it('gives the full amount to a single participant', () => {
        expect(allocateEqualCents(99, [7])).toEqual({ 7: 99 });
    });
});

describe('allocateProportionalCents', () => {
    it('uses floor for non-last participants and remainder on last', () => {
        // 3000 / 10000 * 100 = 30; 7000 gets remainder 70
        const result = allocateProportionalCents(100, [1, 2], { 1: 3000, 2: 7000 });

        expect(result[1]).toBe(30);
        expect(result[2]).toBe(70);
        expect(result[1] + result[2]).toBe(100);
    });

    it('falls back to equal when total shareable is zero', () => {
        const result = allocateProportionalCents(100, [1, 2, 3], { 1: 0, 2: 0, 3: 0 });

        expect(result).toEqual({ 1: 33, 2: 33, 3: 34 });
    });

    it('floors intermediate shares without rounding up', () => {
        // 1/3 * 100 = 33.33… → floor 33; last gets remainder
        const result = allocateProportionalCents(100, [1, 2, 3], { 1: 1, 2: 1, 3: 1 });

        expect(result[1]).toBe(33);
        expect(result[2]).toBe(33);
        expect(result[3]).toBe(34);
    });
});
