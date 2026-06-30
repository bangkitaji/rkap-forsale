<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Searchable;

class ReportGroup extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['code', 'type', 'name', 'description'];

    protected $searchable = ['code', 'name', 'type'];

    public function coaGroups(): HasMany
    {
        return $this->hasMany(CoaGroup::class, 'report_group_id');
    }

    public function cashflowGroups(): HasMany
    {
        return $this->hasMany(CashflowGroup::class, 'report_group_id');
    }
}
