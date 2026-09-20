<?php

namespace App\Models;

use App\Enums\McpOperation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $ledger_id
 * @property string $tool_name
 * @property McpOperation $operation
 * @property array<string, mixed>|null $request_payload
 * @property string $response_status
 * @property int $duration_ms
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class McpActionLog extends Model
{
    /** @use HasFactory<\Database\Factories\McpActionLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'ledger_id',
        'tool_name',
        'operation',
        'request_payload',
        'response_status',
        'duration_ms',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation' => McpOperation::class,
            'request_payload' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
