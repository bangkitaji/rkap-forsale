<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\Searchable;

class Coa extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['code', 'title', 'description', 'coa_group_id'];

    public function coaGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CoaGroup::class, 'coa_group_id');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_coa', 'coa_id', 'activity_id');
    }
}
