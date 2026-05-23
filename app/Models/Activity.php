<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use SoftDeletes;

    protected $fillable = ['work_plan_id', 'code', 'title', 'description'];

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(WorkPlan::class);
    }
}
