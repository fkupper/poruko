<?php

namespace App\Models;

use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ledger_id
 * @property int $user_id
 * @property int|null $payer_account_id
 * @property int|null $destination_account_id
 * @property array<string, mixed> $raw_data
 * @property string|null $suggested_description
 * @property int|null $suggested_amount
 * @property TransactionSplitRule|null $suggested_split_rule
 * @property array<int, array{user_id: int, share?: int|float}>|null $suggested_participants
 * @property \Illuminate\Support\Carbon|null $date
 * @property TransactionSource $source
 * @property float|null $confidence
 * @property string|null $rationale
 * @property PendingTransactionStatus $status
 * @property int|null $reviewed_by_user_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $committed_transaction_id
 * @property-read Ledger $ledger
 * @property-read User $proposer
 * @property-read User|null $reviewer
 * @property-read Account|null $payerAccount
 * @property-read Account|null $destinationAccount
 * @property-read Transaction|null $committedTransaction
 */
class PendingTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\PendingTransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'user_id',
        'payer_account_id',
        'destination_account_id',
        'raw_data',
        'suggested_description',
        'suggested_amount',
        'suggested_split_rule',
        'suggested_participants',
        'date',
        'source',
        'confidence',
        'rationale',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'committed_transaction_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'suggested_amount' => 'integer',
            'suggested_split_rule' => TransactionSplitRule::class,
            'suggested_participants' => 'array',
            'date' => 'date',
            'source' => TransactionSource::class,
            'confidence' => 'float',
            'status' => PendingTransactionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payer_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function committedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'committed_transaction_id');
    }
}
