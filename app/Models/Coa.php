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

    protected $fillable = ['code', 'title', 'title_en', 'description', 'description_en', 'is_gain_loss', 'coa_group_id', 'coa_category_id', 'cashflow_group_id', 'cf_type'];

    protected $casts = [
        'is_gain_loss' => 'boolean',
    ];

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

    /**
     * Determine if a given COA code represents a forex gain/loss toggleable account.
     */
    public static function isForexAccount(?string $code): bool
    {
        if (!$code) {
            return false;
        }

        return in_array(trim((string) $code), static::getForexCodes(), true);
    }

    /**
     * Get all COA codes identified as forex gain/loss accounts (cached for speed).
     */
    public static function getForexCodes(): array
    {
        try {
            return cache()->remember('rkap_forex_coa_codes', 3600, function () {
                $configCodes = config('rkap.forex_coa_codes', ['760301', '7603000001']);
                $dbCodes = static::where('is_gain_loss', true)->pluck('code')->all();
                return array_values(array_unique(array_filter(array_merge((array) $configCodes, $dbCodes))));
            });
        } catch (\Throwable $e) {
            return (array) config('rkap.forex_coa_codes', ['760301', '7603000001']);
        }
    }

    /**
     * Generate SQL condition string for identifying forex accounts in raw queries.
     */
    public static function forexSqlCondition(string $column = 'rkap_budget_items.account_code'): string
    {
        $codes = static::getForexCodes();
        if (empty($codes)) {
            return '1=0';
        }

        $escaped = implode("','", array_map(fn($c) => str_replace("'", "''", (string) $c), $codes));
        return "{$column} IN ('{$escaped}')";
    }

    /**
     * Generate SQL CASE expression to negate amount when is_gain = false for forex accounts.
     */
    public static function forexSignedAmount(
        string $amountColumn,
        string $accountColumn = 'rkap_budget_items.account_code',
        string $isGainColumn = 'rkap_budget_items.is_gain'
    ): string {
        $cond = static::forexSqlCondition($accountColumn);
        return "CASE WHEN {$cond} AND {$isGainColumn} = false THEN -{$amountColumn} ELSE {$amountColumn} END";
    }

}
