<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\Searchable;

class Coa extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['code', 'title', 'description', 'coa_group_id', 'coa_category_id', 'cashflow_group_id'];

    public function coaGroup(): BelongsTo
    {
        return $this->belongsTo(CoaGroup::class, 'coa_group_id');
    }

    /**
     * The P&L category this COA is mapped to.
     */
    public function coaCategory(): BelongsTo
    {
        return $this->belongsTo(CoaCategory::class, 'coa_category_id');
    }

    public function cashflowGroup(): BelongsTo
    {
        return $this->belongsTo(CashflowGroup::class, 'cashflow_group_id');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_coa', 'coa_id', 'activity_id');
    }
}
