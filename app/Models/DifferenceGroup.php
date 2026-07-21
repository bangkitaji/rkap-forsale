<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Searchable;
use App\Models\Traits\Translatable;

class DifferenceGroup extends Model
{
    use SoftDeletes, Searchable, Translatable;

    protected $fillable = ['code', 'name', 'name_en', 'description'];

    protected $searchable = ['code', 'name'];

    public function coas(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Coa::class, 'difference_group_coa', 'difference_group_id', 'coa_id');
    }
}
