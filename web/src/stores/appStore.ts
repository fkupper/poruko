import { create } from 'zustand';

interface AppState {
  currentLedgerId: number | null;
  recentLedgerIds: number[];
  setCurrentLedgerId: (id: number | null) => void;
}

export const useAppStore = create<AppState>()((set) => ({
  currentLedgerId: null,
  recentLedgerIds: [],
  setCurrentLedgerId: (id) =>
    set((state) => {
      if (id === null) {
        return { currentLedgerId: null };
      }

      const withoutDuplicate = state.recentLedgerIds.filter((ledgerId) => ledgerId !== id);
      return {
        currentLedgerId: id,
        recentLedgerIds: [id, ...withoutDuplicate].slice(0, 6),
      };
    }),
}));

