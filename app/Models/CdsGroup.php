<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CdsGroup extends Model
{
    protected $fillable = ['code', 'name'];

    public function coaGroups()
    {
        return $this->hasMany(CoaGroup::class);
    }

    public function cashflowGroups()
    {
        return $this->hasMany(CashflowGroup::class);
    }
}
