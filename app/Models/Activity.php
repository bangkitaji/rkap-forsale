<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\Searchable;

class Activity extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['work_plan_id', 'code', 'title', 'description', 'approval_status', 'requested_by_bureau_id'];

    public function workPlan(): BelongsTo
    {
        return $this->belongsTo(WorkPlan::class);
    }

    public function coas(): BelongsToMany
    {
        return $this->belongsToMany(Coa::class, 'activity_coa', 'activity_id', 'coa_id');
    }

    public function requestedBureau(): BelongsTo
    {
        return $this->belongsTo(Bureau::class, 'requested_by_bureau_id');
    }
}
