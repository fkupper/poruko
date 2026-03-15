<?php

namespace App\Modules\Ledger\Services;

use App\Models\Ledger;

class SettlementSafetyGateService
{
    /**
     * @param array{required_transfers?:array<int, array{amount:int}>} $preview
     * @return array{auto_allowed:bool, reason:?string}
     */
    public function evaluate(Ledger $ledger, array $preview): array
    {
        if (!$ledger->settlement_auto_execute_enabled) {
            return [
                'auto_allowed' => false,
                'reason' => 'auto_execute_disabled',
            ];
        }

        $transfers = $preview['required_transfers'] ?? [];
        $maxTransfer = 0;

        foreach ($transfers as $transfer) {
            $maxTransfer = max($maxTransfer, (int) $transfer['amount']);
        }

        if (count($transfers) > 20) {
            return [
                'auto_allowed' => false,
                'reason' => 'too_many_transfers',
            ];
        }

        if ($maxTransfer > 500_000) {
            return [
                'auto_allowed' => false,
                'reason' => 'transfer_amount_exceeds_threshold',
            ];
        }

        return [
            'auto_allowed' => true,
            'reason' => null,
        ];
    }
}
