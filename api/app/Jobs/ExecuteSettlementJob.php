<?php

namespace App\Jobs;

use App\Models\Ledger;
use App\Modules\Ledger\Actions\ExecuteSettlementAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteSettlementJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $ledgerId,
        public string $periodEnd,
    ) {}

    public function uniqueId(): string
    {
        return app()->environment('testing')
            ? "settlement:{$this->ledgerId}:{$this->periodEnd}:" . uniqid('', true)
            : "settlement:{$this->ledgerId}:{$this->periodEnd}";
    }

    public function handle(ExecuteSettlementAction $action): void
    {
        $ledger = Ledger::query()->findOrFail($this->ledgerId);

        $action->execute($ledger, $this->periodEnd);
    }
}
