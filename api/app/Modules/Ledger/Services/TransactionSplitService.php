<?php

namespace App\Modules\Ledger\Services;

class TransactionSplitService
{
    /**
     * @param list<int> $participantUserIds
     * @param list<array{user_id: int, share?: int|float}> $participantsRaw
     * @param array<int, int> $shareableByUser
     * @return array<int, int> userId => allocated cents
     */
    public function allocateByRule(
        string $splitRule,
        int $amount,
        array $participantUserIds,
        array $participantsRaw,
        array $shareableByUser,
    ): array {
        if (count($participantUserIds) === 0) {
            return [];
        }

        return match ($splitRule) {
            'equal' => $this->allocateEqual($amount, $participantUserIds),
            'proportional' => $this->allocateProportional($amount, $participantUserIds, $shareableByUser),
            'individual' => [$participantUserIds[0] => $amount],
            'manual' => $this->allocateManual($amount, $participantsRaw),
            default => $this->allocateEqual($amount, $participantUserIds),
        };
    }

    /**
     * @param list<int> $participantUserIds
     * @return array<int, int>
     */
    private function allocateEqual(int $amount, array $participantUserIds): array
    {
        $count = count($participantUserIds);
        $base = intdiv($amount, $count);
        $remainder = $amount % $count;
        $lastIndex = $count - 1;

        $result = [];

        foreach ($participantUserIds as $index => $userId) {
            $result[$userId] = $index === $lastIndex
                ? $base + $remainder
                : $base;
        }

        return $result;
    }

    /**
     * @param list<int> $participantUserIds
     * @param array<int, int> $shareableByUser
     * @return array<int, int>
     */
    private function allocateProportional(int $amount, array $participantUserIds, array $shareableByUser): array
    {
        $totalShareable = 0;

        foreach ($participantUserIds as $userId) {
            $totalShareable += $shareableByUser[$userId] ?? 0;
        }

        if ($totalShareable <= 0) {
            return $this->allocateEqual($amount, $participantUserIds);
        }

        $result = [];
        $runningTotal = 0;
        $lastIndex = count($participantUserIds) - 1;

        foreach ($participantUserIds as $index => $userId) {
            if ($index === $lastIndex) {
                $result[$userId] = $amount - $runningTotal;
            } else {
                $allocated = (int) floor(($shareableByUser[$userId] ?? 0) / $totalShareable * $amount);
                $result[$userId] = $allocated;
                $runningTotal += $allocated;
            }
        }

        return $result;
    }

    /**
     * @param list<array{user_id: int, share?: int|float}> $participantsRaw
     * @return array<int, int>
     */
    private function allocateManual(int $amount, array $participantsRaw): array
    {
        $totalShare = 0.0;

        foreach ($participantsRaw as $p) {
            $totalShare += (float) ($p['share'] ?? 0);
        }

        if ($totalShare <= 0) {
            return [];
        }

        $result = [];
        $runningTotal = 0;
        $lastIndex = count($participantsRaw) - 1;

        foreach ($participantsRaw as $index => $p) {
            $userId = (int) $p['user_id'];

            if ($index === $lastIndex) {
                $result[$userId] = $amount - $runningTotal;
            } else {
                $allocated = (int) floor(((float) ($p['share'] ?? 0)) / $totalShare * $amount);
                $result[$userId] = $allocated;
                $runningTotal += $allocated;
            }
        }

        return $result;
    }
}
