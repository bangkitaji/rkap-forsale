<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;

class CoaGroup extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['code', 'name', 'description', 'report_group_id'];

    public function coas(): HasMany
    {
        return $this->hasMany(Coa::class, 'coa_group_id');
    }

    public function reportGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReportGroup::class, 'report_group_id');
    }
}
