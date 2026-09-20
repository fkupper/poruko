import * as React from 'react';
import { useQuery } from '@tanstack/react-query';

import { fetchLedgerMembers } from '@/api/members';
import type { LedgerMember, ParticipantShare, SplitRule } from '@/api/types';
import { useLedgerStore } from '@/stores/ledgerStore';
import {
    allocateEqualCents,
    allocateManualCents,
    allocateProportionalCents,
    isIndividualValid,
    isManualValid,
} from '@/lib/split';

export const SPLIT_RULE_OPTIONS: { value: SplitRule; label: string; hint: string }[] = [
    { value: 'proportional', label: 'Proportional', hint: 'By shareable income' },
    { value: 'equal', label: 'Equal', hint: 'Split evenly' },
    { value: 'individual', label: 'Individual', hint: "One person's cost" },
    { value: 'manual', label: 'Manual', hint: 'Custom ratios (weights)' },
];

export interface ExpenseSplitSeed {
    splitRule: SplitRule;
    participants?: ParticipantShare[];
}

function seedManualDefaults(memberIds: number[]): {
    selected: Set<number>;
    weights: Record<number, number>;
} {
    return {
        selected: new Set(memberIds),
        weights: Object.fromEntries(memberIds.map((id) => [id, 1])),
    };
}

function applySeed(
    seed: ExpenseSplitSeed | null | undefined,
    setters: {
        setSplitRule: (rule: SplitRule) => void;
        setIndividualUserId: (id: number | null) => void;
        setManualSelectedIds: (ids: Set<number>) => void;
        setManualWeights: (weights: Record<number, number>) => void;
        manualInitializedRef: React.MutableRefObject<boolean>;
    },
): void {
    if (!seed) {
        setters.setSplitRule('proportional');
        setters.setIndividualUserId(null);
        setters.setManualSelectedIds(new Set());
        setters.setManualWeights({});
        setters.manualInitializedRef.current = false;
        return;
    }

    setters.setSplitRule(seed.splitRule);
    const participants = seed.participants ?? [];

    if (seed.splitRule === 'individual') {
        setters.setIndividualUserId(participants[0]?.user_id ?? null);
        setters.setManualSelectedIds(new Set());
        setters.setManualWeights({});
        setters.manualInitializedRef.current = false;
        return;
    }

    if (seed.splitRule === 'manual') {
        setters.setIndividualUserId(null);
        setters.setManualSelectedIds(new Set(participants.map((participant) => participant.user_id)));
        const weights: Record<number, number> = {};
        participants.forEach((participant) => {
            weights[participant.user_id] = participant.share && participant.share > 0 ? participant.share : 1;
        });
        setters.setManualWeights(weights);
        setters.manualInitializedRef.current = true;
        return;
    }

    setters.setIndividualUserId(null);
    setters.setManualSelectedIds(new Set());
    setters.setManualWeights({});
    setters.manualInitializedRef.current = false;
}

export function useExpenseSplit(options: {
    open: boolean;
    date?: string | null;
    amountCents: number | null;
    resetToken: string | number;
    seed?: ExpenseSplitSeed | null;
}) {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const [splitRule, setSplitRule] = React.useState<SplitRule>('proportional');
    const [individualUserId, setIndividualUserId] = React.useState<number | null>(null);
    const [manualSelectedIds, setManualSelectedIds] = React.useState<Set<number>>(() => new Set());
    const [manualWeights, setManualWeights] = React.useState<Record<number, number>>({});
    const manualInitializedRef = React.useRef(false);

    const { data: members = [], isPending: isLoadingMembers } = useQuery({
        queryKey: ['members', activeLedgerId, options.date],
        queryFn: () => fetchLedgerMembers(activeLedgerId!, options.date ?? undefined),
        enabled: !!activeLedgerId && options.open,
    });

    const activeMembers = React.useMemo(
        () => members.filter((member: LedgerMember) => member.is_active !== false),
        [members],
    );

    React.useEffect(() => {
        if (!options.open) {
            return;
        }

        applySeed(options.seed, {
            setSplitRule,
            setIndividualUserId,
            setManualSelectedIds,
            setManualWeights,
            manualInitializedRef,
        });
        // Seed only when the dialog identity changes, not when parent objects are recreated.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [options.open, options.resetToken]);

    React.useEffect(() => {
        if (!options.open || activeMembers.length === 0) {
            return;
        }

        if (splitRule === 'individual' && individualUserId === null) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- default to first member when needed
            setIndividualUserId(activeMembers[0].id);
        }

        if (splitRule === 'manual' && !manualInitializedRef.current) {
            manualInitializedRef.current = true;
            const defaults = seedManualDefaults(activeMembers.map((member) => member.id));
            setManualSelectedIds(defaults.selected);
            setManualWeights(defaults.weights);
        }
    }, [options.open, activeMembers, splitRule, individualUserId]);

    const totalShareableIncome = React.useMemo(
        () => activeMembers.reduce((sum, member) => sum + (member.shareable_income || 0), 0),
        [activeMembers],
    );

    const manualParticipants = React.useMemo(
        () =>
            activeMembers
                .filter((member) => manualSelectedIds.has(member.id))
                .map((member) => ({
                    user_id: member.id,
                    share: manualWeights[member.id] ?? 0,
                })),
        [activeMembers, manualSelectedIds, manualWeights],
    );

    const previewAllocations = React.useMemo<Record<number, number>>(() => {
        const amount = options.amountCents || 0;
        if (!activeMembers.length || amount <= 0) {
            return {};
        }

        const participantIds = activeMembers.map((member) => member.id);

        if (splitRule === 'equal') {
            return allocateEqualCents(amount, participantIds);
        }

        if (splitRule === 'proportional') {
            const shareableByUser: Record<number, number> = {};
            activeMembers.forEach((member) => {
                shareableByUser[member.id] = member.shareable_income || 0;
            });
            return allocateProportionalCents(amount, participantIds, shareableByUser);
        }

        if (splitRule === 'individual' && isIndividualValid(individualUserId)) {
            return { [individualUserId!]: amount };
        }

        if (splitRule === 'manual' && isManualValid(manualParticipants)) {
            return allocateManualCents(amount, manualParticipants);
        }

        return {};
    }, [activeMembers, options.amountCents, splitRule, individualUserId, manualParticipants]);

    const payloadParticipants = React.useMemo<ParticipantShare[]>(() => {
        const amount = options.amountCents || 0;

        if (splitRule === 'individual') {
            if (!isIndividualValid(individualUserId)) {
                return [];
            }
            return [{ user_id: individualUserId! }];
        }

        if (splitRule === 'manual') {
            return manualParticipants;
        }

        if (!activeMembers.length || amount <= 0) {
            return [];
        }

        if (splitRule === 'equal') {
            const shares = allocateEqualCents(
                amount,
                activeMembers.map((member) => member.id),
            );
            const equalRatio = 1 / activeMembers.length;
            return activeMembers.map((member) => ({
                user_id: member.id,
                share: shares[member.id] ?? 0,
                share_ratio: equalRatio,
            }));
        }

        const shareableByUser: Record<number, number> = {};
        activeMembers.forEach((member) => {
            shareableByUser[member.id] = member.shareable_income || 0;
        });
        const shares = allocateProportionalCents(
            amount,
            activeMembers.map((member) => member.id),
            shareableByUser,
        );
        return activeMembers.map((member) => ({
            user_id: member.id,
            share: shares[member.id] ?? 0,
            share_ratio:
                totalShareableIncome > 0
                    ? (member.shareable_income || 0) / totalShareableIncome
                    : 1 / activeMembers.length,
        }));
    }, [
        activeMembers,
        options.amountCents,
        splitRule,
        individualUserId,
        manualParticipants,
        totalShareableIncome,
    ]);

    const splitValid =
        ((splitRule === 'equal' || splitRule === 'proportional') && activeMembers.length > 0)
        || (splitRule === 'individual' && isIndividualValid(individualUserId))
        || (splitRule === 'manual' && isManualValid(manualParticipants));

    const handleSplitRuleChange = (value: string) => {
        if (!value) return;
        const next = value as SplitRule;
        setSplitRule(next);

        if (next === 'manual') {
            manualInitializedRef.current = true;
            if (activeMembers.length > 0) {
                const defaults = seedManualDefaults(activeMembers.map((member) => member.id));
                setManualSelectedIds(defaults.selected);
                setManualWeights((previous) => {
                    const nextWeights = { ...defaults.weights };
                    activeMembers.forEach((member) => {
                        if (previous[member.id] && previous[member.id] > 0) {
                            nextWeights[member.id] = previous[member.id];
                        }
                    });
                    return nextWeights;
                });
            } else {
                manualInitializedRef.current = false;
            }
        } else {
            manualInitializedRef.current = false;
        }

        if (next === 'individual' && activeMembers.length > 0 && individualUserId === null) {
            setIndividualUserId(activeMembers[0].id);
        }
    };

    const toggleManualMember = (memberId: number, checked: boolean) => {
        setManualSelectedIds((previous) => {
            const next = new Set(previous);
            if (checked) {
                next.add(memberId);
            } else {
                next.delete(memberId);
            }
            return next;
        });
        if (checked) {
            setManualWeights((previous) => ({
                ...previous,
                [memberId]: previous[memberId] && previous[memberId] > 0 ? previous[memberId] : 1,
            }));
        }
    };

    return {
        splitRule,
        handleSplitRuleChange,
        individualUserId,
        setIndividualUserId,
        manualSelectedIds,
        manualWeights,
        setManualWeights,
        toggleManualMember,
        activeMembers,
        isLoadingMembers,
        previewAllocations,
        payloadParticipants,
        splitValid,
        totalShareableIncome,
        splitHelper: SPLIT_RULE_OPTIONS.find((option) => option.value === splitRule)?.hint ?? '',
        amountCents: options.amountCents,
        showRoundingNote:
            (splitRule === 'equal' || splitRule === 'proportional' || splitRule === 'manual')
            && (splitRule === 'manual' ? manualParticipants.length > 1 : activeMembers.length > 1),
    };
}

export type ExpenseSplitController = ReturnType<typeof useExpenseSplit>;
