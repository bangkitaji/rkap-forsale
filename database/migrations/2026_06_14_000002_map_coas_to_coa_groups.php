<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Coa;
use App\Models\CoaGroup;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $groups = CoaGroup::all()->keyBy('code');

        if ($groups->isEmpty()) {
            return;
        }

        Coa::chunk(200, function ($coas) use ($groups) {
            foreach ($coas as $coa) {
                $code = $coa->code;
                $targetGroupCode = null;

                if (str_starts_with($code, '1')) {
                    $targetGroupCode = '100000'; // Aset
                } elseif (str_starts_with($code, '2')) {
                    $targetGroupCode = '200000'; // Liabilitas
                } elseif (str_starts_with($code, '3')) {
                    $targetGroupCode = '300000'; // Ekuitas
                } elseif (str_starts_with($code, '4')) {
                    $targetGroupCode = '400000'; // Pendapatan
                } elseif (str_starts_with($code, '64')) {
                    $targetGroupCode = '510000'; // Beban Pegawai
                } elseif (str_starts_with($code, '8')) {
                    $targetGroupCode = '530000'; // Beban Investasi / CAPEX
                } elseif (str_starts_with($code, '7')) {
                    $targetGroupCode = '600000'; // Non-Operating
                } elseif (str_starts_with($code, '5') || str_starts_with($code, '6') || str_starts_with($code, '9')) {
                    $targetGroupCode = '520000'; // Beban Operasional / OPEX
                }

                if ($targetGroupCode && isset($groups[$targetGroupCode])) {
                    $coa->coa_group_id = $groups[$targetGroupCode]->id;
                    $coa->save();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Coa::query()->update(['coa_group_id' => null]);
    }
};
