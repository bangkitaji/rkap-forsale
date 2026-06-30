<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CfLineItem extends Model
{
    protected $table = 'cf_line_items';

    protected $primaryKey = 'item_code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'item_code',
        'category_id',
        'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CfCategory::class, 'category_id', 'category_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(CashFlowFact::class, 'item_code', 'item_code');
    }
}
