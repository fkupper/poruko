import * as React from 'react';

const MOBILE_BREAKPOINT = 768;

function subscribe(onStoreChange: () => void): () => void {
    const mql = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);
    mql.addEventListener('change', onStoreChange);
    return () => mql.removeEventListener('change', onStoreChange);
}

function getSnapshot(): boolean {
    return window.innerWidth < MOBILE_BREAKPOINT;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return React.useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
