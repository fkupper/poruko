/**
 * Allocate cents across participants — mirrors backend TransactionSplitService.
 */

export type ManualParticipantWeight = {
    user_id: number;
    share: number;
};

export function allocateEqualCents(amountCents: number, participantUserIds: number[]): Record<number, number> {
    if (participantUserIds.length === 0) {
        return {};
    }

    const count = participantUserIds.length;
    const base = Math.floor(amountCents / count);
    const remainder = amountCents % count;
    const lastIndex = count - 1;
    const result: Record<number, number> = {};

    participantUserIds.forEach((userId, index) => {
        result[userId] = index === lastIndex ? base + remainder : base;
    });

    return result;
}

export function allocateProportionalCents(
    amountCents: number,
    participantUserIds: number[],
    shareableByUser: Record<number, number>,
): Record<number, number> {
    if (participantUserIds.length === 0) {
        return {};
    }

    let totalShareable = 0;
    for (const userId of participantUserIds) {
        totalShareable += shareableByUser[userId] ?? 0;
    }

    if (totalShareable <= 0) {
        return allocateEqualCents(amountCents, participantUserIds);
    }

    const result: Record<number, number> = {};
    let runningTotal = 0;
    const lastIndex = participantUserIds.length - 1;

    participantUserIds.forEach((userId, index) => {
        if (index === lastIndex) {
            result[userId] = amountCents - runningTotal;
        } else {
            const allocated = Math.floor(((shareableByUser[userId] ?? 0) / totalShareable) * amountCents);
            result[userId] = allocated;
            runningTotal += allocated;
        }
    });

    return result;
}

/** Weight-based manual split — mirrors backend allocateManual (last participant gets remainder). */
export function allocateManualCents(
    amountCents: number,
    participants: ManualParticipantWeight[],
): Record<number, number> {
    if (participants.length === 0) {
        return {};
    }

    let totalShare = 0;
    for (const p of participants) {
        totalShare += p.share;
    }

    if (totalShare <= 0) {
        return {};
    }

    const result: Record<number, number> = {};
    let runningTotal = 0;
    const lastIndex = participants.length - 1;

    participants.forEach((p, index) => {
        if (index === lastIndex) {
            result[p.user_id] = amountCents - runningTotal;
        } else {
            const allocated = Math.floor((p.share / totalShare) * amountCents);
            result[p.user_id] = allocated;
            runningTotal += allocated;
        }
    });

    return result;
}

export function isIndividualValid(userId: number | null | undefined): boolean {
    return typeof userId === 'number' && Number.isFinite(userId) && userId > 0;
}

export function isManualValid(participants: ManualParticipantWeight[]): boolean {
    return participants.length >= 1 && participants.every((p) => p.share > 0);
}
