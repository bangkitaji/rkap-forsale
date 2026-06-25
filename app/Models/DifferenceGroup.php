<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Searchable;

class DifferenceGroup extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['code', 'name', 'description'];

    protected $searchable = ['code', 'name'];

    public function coas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Coa::class, 'difference_group_id');
    }
}
