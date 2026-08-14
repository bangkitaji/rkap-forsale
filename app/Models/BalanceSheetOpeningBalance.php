<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceSheetOpeningBalance extends Model
{
    protected $fillable = [
        'rkap_period_id',
        'coa_id',
        'amount',
        'created_by',
        'updated_by',
    ];

    public function rkapPeriod()
    {
        return $this->belongsTo(RkapPeriod::class, 'rkap_period_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }}
