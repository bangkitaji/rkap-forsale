<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoaCategory extends Model
{
    protected $fillable = ['key', 'label', 'group', 'color', 'sort_order'];

    /**
     * COAs assigned to this P&L category.
     */
    public function coas(): HasMany
    {
        return $this->hasMany(Coa::class, 'coa_category_id');
    }

    /**
     * Scope to order by the defined sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
