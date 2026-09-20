<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $ledger_id
 * @property int $user_id
 * @property string $role
 * @property int|null $main_personal_account_id
 * @property int|null $default_payment_account_id
 * @property int|null $default_expense_account_id
 * @property bool $ai_import_auto_create_accounts
 * @property bool $mcp_enabled
 * @property bool $mcp_allow_read
 * @property bool $mcp_allow_write
 * @property bool $mcp_allow_destructive
 * @property string $mcp_post_mode
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class LedgerUser extends Pivot
{
    use SoftDeletes;

    public $incrementing = true;

    protected $table = 'ledger_user';

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'user_id',
        'role',
        'main_personal_account_id',
        'default_payment_account_id',
        'default_expense_account_id',
        'ai_import_auto_create_accounts',
        'mcp_enabled',
        'mcp_allow_read',
        'mcp_allow_write',
        'mcp_allow_destructive',
        'mcp_post_mode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'main_personal_account_id' => 'integer',
            'default_payment_account_id' => 'integer',
            'default_expense_account_id' => 'integer',
            'ai_import_auto_create_accounts' => 'boolean',
            'mcp_enabled' => 'boolean',
            'mcp_allow_read' => 'boolean',
            'mcp_allow_write' => 'boolean',
            'mcp_allow_destructive' => 'boolean',
            'mcp_post_mode' => \App\Enums\McpPostMode::class,
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function mainPersonalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'main_personal_account_id');
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
}
