<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'directorate_id',
        'code',
        'name',
        'description',
        'is_verifier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_verifier' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }

    public function bureaus(): HasMany
    {
        return $this->hasMany(Bureau::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerifier($query)
    {
        return $query->where('is_verifier', true);
    }
}
