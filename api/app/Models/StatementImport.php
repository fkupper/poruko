<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $ledger_id
 * @property int $user_id
 * @property string $status
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $file_size
 * @property int $parsed_count
 * @property int $pending_count
 * @property int $duplicate_count
 * @property int $failed_count
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property-read Ledger $ledger
 * @property-read User $user
 */
class StatementImport extends Model
{
    /** @use HasFactory<\Database\Factories\StatementImportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'ledger_id',
        'user_id',
        'status',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'parsed_count',
        'pending_count',
        'duplicate_count',
        'failed_count',
        'error_message',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'parsed_count' => 'integer',
            'pending_count' => 'integer',
            'duplicate_count' => 'integer',
            'failed_count' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StatementImportEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(StatementImportEntry::class);
    }
}
