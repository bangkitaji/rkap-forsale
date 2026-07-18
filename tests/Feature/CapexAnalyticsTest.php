<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\RkapPeriod;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CapexAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Analytics User',
            'email' => 'analytics@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_guest_cannot_access_capex_analytics(): void
    {
        $response = $this->get('/analytics/capex');
        $response->assertRedirect('/login');
    }

    public function test_user_can_access_capex_analytics(): void
    {
        // Create a period
        RkapPeriod::create([
            'title' => 'Periode RKAP 2026',
            'year' => 2026,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)->get('/analytics/capex');
        $response->assertStatus(200);
        $response->assertSee('Laporan Capex');
        $response->assertSee('Anggaran Capex');
        $response->assertSee('Realisasi Capex YTD');
        $response->assertSee('Proyeksi Akhir Tahun');
    }

    public function test_user_can_access_coa_detail_ajax(): void
    {
        $period = RkapPeriod::create([
            'title' => 'Periode RKAP 2026',
            'year' => 2026,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)->get('/analytics/coa-detail?coa_code=1201010001&period_id=' . $period->id);
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
