<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RkapActivityFile extends Model
{
    protected $fillable = [
        'rkap_work_plan_id',
        'file_name',
        'original_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function rkapWorkPlan(): BelongsTo
    {
        return $this->belongsTo(RkapWorkPlan::class, 'rkap_work_plan_id');
    }
}
