<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Searchable;

class Satuan extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = ['name', 'description'];
}
