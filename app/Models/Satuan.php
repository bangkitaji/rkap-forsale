<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Searchable;
use App\Models\Traits\Translatable;

class Satuan extends Model
{
    use SoftDeletes, Searchable, Translatable;

    protected $fillable = ['name', 'name_en', 'description'];
}
