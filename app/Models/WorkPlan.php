<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;
use App\Models\Traits\Translatable;

class WorkPlan extends Model
{
    use SoftDeletes, Searchable, Translatable;

    protected $fillable = ['code', 'title', 'title_en', 'approval_status', 'rejection_note', 'requested_by_bureau_id'];

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function requestedBureau(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bureau::class, 'requested_by_bureau_id');
    }
}
