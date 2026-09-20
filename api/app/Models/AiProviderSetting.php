<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property AiProvider $provider
 * @property string|null $base_url
 * @property string $api_key
 * @property string $api_key_last_four
 * @property string|null $model
 * @property-read User $user
 */
class AiProviderSetting extends Model
{
    /** @use HasFactory<\Database\Factories\AiProviderSettingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'provider',
        'base_url',
        'api_key',
        'api_key_last_four',
        'model',
    ];

    /** @var list<string> */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
