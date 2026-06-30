<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialVersion extends Model
{
    protected $table = 'financial_versions';

    protected $primaryKey = 'version_id';

    protected $fillable = [
        'name',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function facts(): HasMany
    {
        return $this->hasMany(CashFlowFact::class, 'version_id', 'version_id');
    }
}
