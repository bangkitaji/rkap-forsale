<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Support\Facades\Hash;
use App\Livewire\Auth\ChangePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword'),
        ]);
    }

    public function test_guest_cannot_access_change_password(): void
    {
        $response = $this->get('/change-password');
        $response->assertRedirect('/login');
    }

    public function test_user_can_access_change_password(): void
    {
        $response = $this->actingAs($this->user)->get('/change-password');
        $response->assertStatus(200);
    }

    public function test_incorrect_current_password_shows_validation_error(): void
    {
        Livewire::actingAs($this->user)
            ->test(ChangePassword::class)
            ->set('current_password', 'wrongpassword')
            ->set('new_password', 'newsecret')
            ->set('new_password_confirmation', 'newsecret')
            ->call('changePassword')
            ->assertHasErrors(['current_password' => 'Password saat ini tidak cocok.']);
    }

    public function test_new_password_validation_fails_if_too_short(): void
    {
        Livewire::actingAs($this->user)
            ->test(ChangePassword::class)
            ->set('current_password', 'oldpassword')
            ->set('new_password', 'short')
            ->set('new_password_confirmation', 'short')
            ->call('changePassword')
            ->assertHasErrors(['new_password']);
    }

    public function test_new_password_validation_fails_if_mismatched_confirmation(): void
    {
        Livewire::actingAs($this->user)
            ->test(ChangePassword::class)
            ->set('current_password', 'oldpassword')
            ->set('new_password', 'newsecret')
            ->set('new_password_confirmation', 'different')
            ->call('changePassword')
            ->assertHasErrors(['new_password']);
    }

    public function test_password_can_be_changed_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(ChangePassword::class)
            ->set('current_password', 'oldpassword')
            ->set('new_password', 'newsecretpassword')
            ->set('new_password_confirmation', 'newsecretpassword')
            ->call('changePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '')
            ->assertSet('new_password', '')
            ->assertSet('new_password_confirmation', '');

        $this->assertTrue(Hash::check('newsecretpassword', $this->user->fresh()->password));
    }
}
