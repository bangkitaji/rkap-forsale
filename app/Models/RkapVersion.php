<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rkap_submission_id',
        'version_number',
        'created_by',
        'change_type',
        'change_reason',
        'total_budget',
        'snapshot_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'total_budget' => 'decimal:2',
            'snapshot_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(RkapSubmission::class, 'rkap_submission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::creating(function (RkapVersion $version) {
            $version->created_at = $version->created_at ?? now();
        });
    }

    public function getChangeTypeLabelAttribute(): string
    {
        return match ($this->change_type) {
            'initial' => 'Pengajuan Awal',
            'revision' => 'Revisi',
            'dept_revision' => 'Revisi atas permintaan Kadep',
            'dir_revision' => 'Revisi atas permintaan Direksi',
            'final_revision' => 'Revisi atas permintaan Verifikator',
            default => $this->change_type,
        };
    }
}
