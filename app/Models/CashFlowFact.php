<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowFact extends Model
{
    protected $table = 'cash_flow_facts';

    protected $primaryKey = 'fact_id';

    protected $fillable = [
        'item_code',
        'version_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(CfLineItem::class, 'item_code', 'item_code');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FinancialVersion::class, 'version_id', 'version_id');
    }
}
