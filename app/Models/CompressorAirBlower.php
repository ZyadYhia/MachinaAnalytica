<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompressorAirBlower extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'flow',
        'temperature',
        'pressure',
        'vibration',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'flow' => 'float',
            'temperature' => 'float',
            'pressure' => 'float',
            'vibration' => 'float',
        ];
    }
}
