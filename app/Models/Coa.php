<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\Searchable;
use App\Models\Traits\Translatable;

class Coa extends Model
{
    use SoftDeletes, Searchable, Translatable;

    protected $fillable = ['code', 'title', 'title_en', 'description', 'description_en', 'coa_group_id', 'coa_category_id', 'cashflow_group_id', 'cf_type'];

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

    public function differenceGroups(): BelongsToMany
    {
        return $this->belongsToMany(DifferenceGroup::class, 'difference_group_coa', 'coa_id', 'difference_group_id');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_coa', 'coa_id', 'activity_id');
    }

    /**
     * Check if this COA is a revenue (pendapatan) account.
     */
    public function isRevenue(): bool
    {
        if ($this->relationLoaded('coaCategory') && $this->coaCategory) {
            return $this->coaCategory->group === 'Revenue' || $this->coaCategory->key === 'non_operating_revenue';
        }

        // Fallback check in case relation isn't loaded or category is null
        $category = $this->coaCategory()->first();
        if ($category) {
            return $category->group === 'Revenue' || $category->key === 'non_operating_revenue';
        }

        // Fallback to code prefix rules
        return str_starts_with($this->code, '4') || str_starts_with($this->code, '71');
    }
}
