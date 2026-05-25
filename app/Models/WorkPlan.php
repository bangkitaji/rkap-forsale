<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;

class WorkPlan extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['code', 'title'];

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
