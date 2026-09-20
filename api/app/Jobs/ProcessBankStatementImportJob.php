<?php

namespace App\Jobs;

use App\Enums\StatementImportStage;
use App\Models\StatementImport;
use App\Modules\Ledger\Actions\ProcessStatementImportAction;
use App\Modules\Ledger\Services\StatementImportLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessBankStatementImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = StatementImportLimits::JOB_TIMEOUT_SECONDS;

    public int $uniqueFor = StatementImportLimits::UNIQUE_LOCK_SECONDS;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $statementImportId,
    ) {}

    public function uniqueId(): string
    {
        return "statement-import:{$this->statementImportId}";
    }

    public function handle(ProcessStatementImportAction $action): void
    {
        $import = StatementImport::query()->findOrFail($this->statementImportId);
        $import->update([
            'status' => 'processing',
            'stage' => StatementImportStage::Parsing->value,
            'progress_current' => 0,
            'progress_total' => 0,
            'error_message' => null,
        ]);

        $action->execute($import);
        Storage::disk('local')->delete($import->file_path);
    }

    public function failed(Throwable $exception): void
    {
        $import = StatementImport::query()->find($this->statementImportId);

        if (!$import instanceof StatementImport) {
            return;
        }

        $import->update([
            'status' => 'failed',
            'stage' => StatementImportStage::Failed->value,
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            'processed_at' => now(),
        ]);

        Storage::disk('local')->delete($import->file_path);
    }
}
