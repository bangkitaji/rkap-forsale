<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CfCategory extends Model
{
    protected $table = 'cf_categories';

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'name',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(CfLineItem::class, 'category_id', 'category_id');
    }
}
