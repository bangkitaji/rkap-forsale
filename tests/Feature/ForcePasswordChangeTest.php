<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_not_redirected_by_middleware(): void
    {
        // Unauthenticated user should not be redirected by the middleware (they will hit the auth guard redirect)
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_user_with_must_change_password_is_redirected_to_security_tab(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        // Manually authenticate using $this->be() to bypass actingAs in-memory override
        $this->be($user);

        $response = $this->get('/');
        $response->assertRedirect('/my-profile/security');
        $response->assertSessionHas('force_password_change');
    }

    public function test_user_with_must_change_password_can_access_security_tab(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->be($user);

        $response = $this->get('/my-profile/security');
        $response->assertStatus(200);
    }

    public function test_user_with_must_change_password_cannot_access_profile_tab(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->be($user);

        // my-profile defaults to profile tab, which should be redirected
        $response = $this->get('/my-profile');
        $response->assertRedirect('/my-profile/security');
    }

    public function test_user_without_must_change_password_is_not_redirected(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
        ]);

        $this->be($user);

        $response = $this->get('/');
        $response->assertOk();
    }

    public function test_user_password_change_sets_must_change_password_to_false(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old_password'),
            'must_change_password' => true,
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Auth\MyProfile::class, ['tab' => 'security'])
            ->set('current_password', 'old_password')
            ->set('new_password', 'new_password123')
            ->set('new_password_confirmation', 'new_password123')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
