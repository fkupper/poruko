import { create } from 'zustand';

interface AppState {
  currentLedgerId: string | null;
  setCurrentLedgerId: (id: string | null) => void;
}

export const useAppStore = create<AppState>()((set) => ({
  currentLedgerId: null,
  setCurrentLedgerId: (id) => set({ currentLedgerId: id }),
}));

