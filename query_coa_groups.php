<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$mappings = App\Models\CoaGroup::with('reportGroup')->get()->map(function($cg) {
    return [
        'code' => $cg->code,
        'name' => $cg->name,
        'rg_code' => $cg->reportGroup?->code,
        'rg_name' => $cg->reportGroup?->name
    ];
})->toArray();

print_r($mappings);
