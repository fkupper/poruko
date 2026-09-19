<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $statement_import_id
 * @property int $ledger_id
 * @property string $transaction_fingerprint
 * @property string $status
 * @property array<string, mixed> $raw_data
 * @property int|null $pending_transaction_id
 * @property-read StatementImport $statementImport
 * @property-read Ledger $ledger
 * @property-read PendingTransaction|null $pendingTransaction
 */
class StatementImportEntry extends Model
{
    /** @use HasFactory<\Database\Factories\StatementImportEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'statement_import_id',
        'ledger_id',
        'transaction_fingerprint',
        'status',
        'raw_data',
        'pending_transaction_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
        ];
    }

    /** @return BelongsTo<StatementImport, $this> */
    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<PendingTransaction, $this> */
    public function pendingTransaction(): BelongsTo
    {
        return $this->belongsTo(PendingTransaction::class);
    }
}
