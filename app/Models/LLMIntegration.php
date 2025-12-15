<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LLMIntegration extends Model
{
    /** @use HasFactory<\Database\Factories\LLMIntegrationFactory> */
    use HasFactory;

    protected $table = 'llm_integrations';

    protected $fillable = [
        'user_id',
        'active_integration',
        'integration_status',
        'active_model',
        'model_provider',
        'chat_mode',
        'last_health_check_at',
        'provider_config',
    ];

    protected function casts(): array
    {
        return [
            'last_health_check_at' => 'datetime',
            'provider_config' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOnline(): bool
    {
        return $this->integration_status === 'online';
    }

    public function isOffline(): bool
    {
        return $this->integration_status === 'offline';
    }

    public function isSyncMode(): bool
    {
        return $this->chat_mode === 'sync';
    }

    public function isAsyncMode(): bool
    {
        return $this->chat_mode === 'async';
    }
}
