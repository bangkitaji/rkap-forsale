<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Rkap\RkapBulkUpload;
use App\Models\RkapPeriod;
use App\Models\Bureau;
use App\Models\User;
use App\Models\RkapSubmission;
use App\Models\WorkPlan;
use App\Models\Activity;
use App\Models\Coa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RkapBulkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RkapPeriod $period;
    protected Bureau $bureau;
    protected WorkPlan $workPlan;
    protected Activity $activity;
    protected Coa $coa;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions and roles setup
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'kepala_biro']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'rkap.create', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::findByName('kepala_biro');
        $role->givePermissionTo($permission);

        // Setup organization
        $directorate = \App\Models\Directorate::create([
            'code' => 'DIR01',
            'name' => 'Directorate Test',
            'is_active' => true,
        ]);

        $department = \App\Models\Department::create([
            'directorate_id' => $directorate->id,
            'code' => 'DEP01',
            'name' => 'Department Test',
            'is_active' => true,
        ]);

        $this->bureau = Bureau::create([
            'department_id' => $department->id,
            'code' => 'BUR01',
            'name' => 'Bureau Test',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'bureau_id' => $this->bureau->id,
        ]);
        $this->user->assignRole('kepala_biro');

        // Setup RKAP Period
        $this->period = RkapPeriod::create([
            'year' => 2026,
            'title' => 'RKAP 2026',
            'status' => 'open',
            'submission_start' => now()->subDay(),
            'submission_end' => now()->addDay(),
        ]);

        // Setup WorkPlan & Activity
        $this->workPlan = WorkPlan::create([
            'code' => 'WP001',
            'title' => 'Test Work Plan',
            'approval_status' => 'approved',
        ]);

        $this->activity = Activity::create([
            'work_plan_id' => $this->workPlan->id,
            'code' => 'ACT001',
            'title' => 'Test Activity',
            'approval_status' => 'approved',
        ]);

        $this->coa = Coa::create([
            'code' => '510101',
            'title' => 'Gaji Karyawan',
        ]);

        $this->activity->coas()->attach($this->coa->id);
    }

    public function test_bulk_upload_appends_to_existing_draft(): void
    {
        $this->actingAs($this->user);

        // 1. Create an existing draft submission
        $submission = RkapSubmission::create([
            'rkap_period_id' => $this->period->id,
            'bureau_id' => $this->bureau->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'total_budget' => 100,
        ]);

        // Create temporary Excel file matching RkapBulkUpload expected structure
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pengajuan');

        // Row 1: Bureau ID
        $sheet->setCellValue('A1', 'ID Biro');
        $sheet->setCellValue('B1', $this->bureau->id);

        // Row 2: Period ID
        $sheet->setCellValue('A2', 'ID Periode');
        $sheet->setCellValue('B2', $this->period->id);

        // Row 4: Headers
        $headers = [
            'work_plan_code', 'work_plan_name',
            'activity_code', 'activity_name',
            'coa_code', 'coa_name',
            'unit', 'quantity', 'unit_2', 'quantity_2',
            'unit_price', 'remarks',
            'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
            'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10', 'co11', 'co12',
        ];
        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValueExplicit(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '4',
                $header,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        // Row 6: Data Row
        $row = [
            'WP001', 'Test Work Plan',
            'ACT001', 'Test Activity',
            '510101', 'Gaji Karyawan',
            'Unit', '2', '', '',
            '5000', 'Excel uploaded item',
            '5000', '5000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
            '5000', '5000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
        ];
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '6',
                $val
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'rkap_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $excelContent = file_get_contents($tempPath);
        $uploadedFile = UploadedFile::fake()->createWithContent('template.xlsx', $excelContent);

        // Run bulk upload component
        Livewire::test(RkapBulkUpload::class, ['periodId' => $this->period->id])
            ->upload('file', [$uploadedFile])
            ->call('uploadAndParse')
            ->assertSet('parsed', true)
            ->call('saveAsDraft')
            ->assertSet('imported', true);

        // Verify it was appended to the same draft submission
        $this->assertEquals(1, RkapSubmission::count());
        $freshSubmission = $submission->fresh();
        $this->assertEquals(10000, $freshSubmission->total_budget); // 2 * 5000 = 10000

        unlink($tempPath);
    }

    public function test_revenue_coa_cash_out_can_exceed_budget(): void
    {
        $this->actingAs($this->user);

        // 1. Create a revenue COA mapped to activity
        $revenueCoa = Coa::create([
            'code' => '410101',
            'title' => 'Pendapatan Tiket Utama',
        ]);
        $this->activity->coas()->attach($revenueCoa->id);

        // 2. Create Excel file with cash out exceeding budget (Total budget = 2 * 5000 = 10000. Cash out = 15000)
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pengajuan');

        $sheet->setCellValue('A1', 'ID Biro');
        $sheet->setCellValue('B1', $this->bureau->id);
        $sheet->setCellValue('A2', 'ID Periode');
        $sheet->setCellValue('B2', $this->period->id);

        $headers = [
            'work_plan_code', 'work_plan_name',
            'activity_code', 'activity_name',
            'coa_code', 'coa_name',
            'unit', 'quantity', 'unit_2', 'quantity_2',
            'unit_price', 'remarks',
            'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
            'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10', 'co11', 'co12',
        ];
        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValueExplicit(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '4',
                $header,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        // Data Row with cash out (co1=7500, co2=7500 => total 15000) exceeding budget (qty=2, price=5000 => total 10000)
        $row = [
            'WP001', 'Test Work Plan',
            'ACT001', 'Test Activity',
            '410101', 'Pendapatan Tiket Utama',
            'Unit', '2', '', '',
            '5000', 'Revenue item',
            '5000', '5000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
            '7500', '7500', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
        ];
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '6',
                $val
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'rkap_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $excelContent = file_get_contents($tempPath);
        $uploadedFile = UploadedFile::fake()->createWithContent('template.xlsx', $excelContent);

        // Run bulk upload component and expect it to succeed without validation errors
        Livewire::test(RkapBulkUpload::class, ['periodId' => $this->period->id])
            ->upload('file', [$uploadedFile])
            ->call('uploadAndParse')
            ->assertSet('parsed', true)
            ->call('saveAsDraft')
            ->assertSet('imported', true);

        $this->assertEquals(1, RkapSubmission::count());
        $freshSubmission = RkapSubmission::first();
        $this->assertEquals(10000, $freshSubmission->total_budget);

        unlink($tempPath);
    }

    public function test_expense_coa_cash_out_exceed_budget_fails(): void
    {
        $this->actingAs($this->user);

        // Create Excel file with cash out exceeding budget (Total budget = 2 * 5000 = 10000. Cash out = 15000)
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pengajuan');

        $sheet->setCellValue('A1', 'ID Biro');
        $sheet->setCellValue('B1', $this->bureau->id);
        $sheet->setCellValue('A2', 'ID Periode');
        $sheet->setCellValue('B2', $this->period->id);

        $headers = [
            'work_plan_code', 'work_plan_name',
            'activity_code', 'activity_name',
            'coa_code', 'coa_name',
            'unit', 'quantity', 'unit_2', 'quantity_2',
            'unit_price', 'remarks',
            'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
            'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10', 'co11', 'co12',
        ];
        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValueExplicit(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '4',
                $header,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        // Data Row: code 510101 (expense), qty=2, price=5000 (total 10000), cashout = 15000
        $row = [
            'WP001', 'Test Work Plan',
            'ACT001', 'Test Activity',
            '510101', 'Gaji Karyawan',
            'Unit', '2', '', '',
            '5000', 'Expense item',
            '5000', '5000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
            '7500', '7500', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
        ];
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '6',
                $val
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'rkap_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $excelContent = file_get_contents($tempPath);
        $uploadedFile = UploadedFile::fake()->createWithContent('template.xlsx', $excelContent);

        // Run bulk upload component and expect it to have validation error
        Livewire::test(RkapBulkUpload::class, ['periodId' => $this->period->id])
            ->upload('file', [$uploadedFile])
            ->call('uploadAndParse')
            ->assertSet('parsed', false)
            ->assertSee('melebihi total item');

        unlink($tempPath);
    }

    public function test_bulk_upload_trims_codes_with_whitespace(): void
    {
        $this->actingAs($this->user);

        // Create Excel file with spaces at front/back of codes
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pengajuan');

        $sheet->setCellValue('A1', 'ID Biro');
        $sheet->setCellValue('B1', $this->bureau->id);
        $sheet->setCellValue('A2', 'ID Periode');
        $sheet->setCellValue('B2', $this->period->id);

        $headers = [
            'work_plan_code', 'work_plan_name',
            'activity_code', 'activity_name',
            'coa_code', 'coa_name',
            'unit', 'quantity', 'unit_2', 'quantity_2',
            'unit_price', 'remarks',
            'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
            'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10', 'co11', 'co12',
        ];
        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValueExplicit(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '4',
                $header,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        // Data Row: work plan code, activity code, coa code have whitespace/non-breaking spaces (e.g. \u{A0})
        $row = [
            "  WP001 \u{A0}", 'Test Work Plan',
            " \u{A0} ACT001  ", 'Test Activity',
            "  510101 \u{A0} ", 'Gaji Karyawan',
            'Unit', '2', '', '',
            '5000', 'Expense item with spaces',
            '5000', '5000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
            '5000', '5000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0',
        ];
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '6',
                $val
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'rkap_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $excelContent = file_get_contents($tempPath);
        $uploadedFile = UploadedFile::fake()->createWithContent('template.xlsx', $excelContent);

        // Run bulk upload component and expect it to succeed because spaces are trimmed robustly
        Livewire::test(RkapBulkUpload::class, ['periodId' => $this->period->id])
            ->upload('file', [$uploadedFile])
            ->call('uploadAndParse')
            ->assertSet('parsed', true)
            ->call('saveAsDraft')
            ->assertSet('imported', true);

        $this->assertEquals(1, RkapSubmission::count());
        unlink($tempPath);
    }
}
