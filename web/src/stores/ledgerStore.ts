import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface LedgerState {
    activeLedgerId: number | null;
    setActiveLedgerId: (id: number | null) => void;
}

export const useLedgerStore = create<LedgerState>()(
    persist(
        (set) => ({
            activeLedgerId: null,
            setActiveLedgerId: (id) => set({ activeLedgerId: id }),
        }),
        {
            name: 'poruko-ledger-storage',
        }
    )
);
