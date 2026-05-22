<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RkapComment extends Model
{
    protected $fillable = [
        'rkap_submission_id',
        'user_id',
        'version_number',
        'content',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'rkap_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RkapComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(RkapComment::class, 'parent_id')->orderBy('created_at');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
