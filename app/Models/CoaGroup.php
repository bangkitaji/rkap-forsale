<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;
use App\Models\Traits\Translatable;

class CoaGroup extends Model
{
    use SoftDeletes, Searchable, Translatable;

    protected $fillable = ['code', 'name', 'name_en', 'description', 'report_group_id', 'cds_group_id'];

    public function coas(): HasMany
    {
        return $this->hasMany(Coa::class, 'coa_group_id');
    }

    public function reportGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReportGroup::class, 'report_group_id');
    }

    public function cdsGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CdsGroup::class, 'cds_group_id');
    }
}
