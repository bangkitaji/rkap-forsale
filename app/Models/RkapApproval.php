<?php

namespace App\Models;

use App\Enums\ApprovalAction;
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
        $enum = ApprovalAction::tryFrom($this->action);
        return $enum ? $enum->label() : $this->action;
    }

    public function getActionColorAttribute(): string
    {
        $enum = ApprovalAction::tryFrom($this->action);
        return $enum ? $enum->color() : 'secondary';
    }
}
