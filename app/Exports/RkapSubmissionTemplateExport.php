<?php

namespace App\Exports;

use App\Models\Activity;
use App\Models\Coa;
use App\Models\WorkPlan;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Generates the RKAP bulk submission template (.xlsx) with 3 sheets:
 *   1. Data Pengajuan — the main data entry sheet
 *   2. Referensi      — lookup lists of WorkPlans, Activities, COAs
 *   3. Petunjuk       — instructions for filling the template
 */
class RkapSubmissionTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Data Pengajuan' => new RkapSubmissionTemplateDataSheet(),
            'Referensi'      => new RkapSubmissionTemplateReferenceSheet(),
            'Petunjuk'       => new RkapSubmissionTemplateInstructionSheet(),
        ];
    }
}
