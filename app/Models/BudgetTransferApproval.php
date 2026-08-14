<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransferApproval extends Model
{
    protected $fillable = [
        'budget_transfer_id',
        'user_id',
        'stage',
        'action',
        'comments',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BudgetTransfer::class, 'budget_transfer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStageLabelAttribute(): string
    {
        return match ($this->stage) {
            'source_department' => 'Kepala Departemen Pengusul',
            'target_department' => 'Kepala Departemen Penerima',
            'target_bureau' => 'Kepala Biro Penerima',
            default => ucfirst(str_replace('_', ' ', $this->stage)),
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => ucfirst($this->action),
        };
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }
}
