<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LLMSetting extends Model
{
    /** @use HasFactory<\Database\Factories\LLMSettingFactory> */
    use HasFactory;

    protected $table = 'llm_settings';

    protected $fillable = [
        'user_id',
        'provider',
        'key',
        'value',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $this->is_encrypted && $value
                ? decrypt($value)
                : $value,
            set: fn(?string $value) => $this->is_encrypted && $value
                ? encrypt($value)
                : $value,
        );
    }
}
