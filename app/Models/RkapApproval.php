<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapApproval extends Model
{
    protected $fillable = [
        'rkap_submission_id',
        'user_id',
        'version_number',
        'role',
        'action',
        'comments',
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

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revision_requested' => 'Minta Revisi',
            default => $this->action,
        };
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'approved' => 'success',
            'rejected' => 'danger',
            'revision_requested' => 'warning',
            default => 'secondary',
        };
    }
}
