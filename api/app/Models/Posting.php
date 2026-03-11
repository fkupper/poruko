<?php

namespace App\Models;

use App\Enums\PostingDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Posting extends Model
{
    /** @use HasFactory<\Database\Factories\PostingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'account_id',
        'amount',
        'direction',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'direction' => PostingDirection::class,
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
