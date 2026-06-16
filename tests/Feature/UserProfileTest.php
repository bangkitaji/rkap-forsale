<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Support\Facades\Hash;
use App\Livewire\Auth\MyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserProfileTest extends TestCase
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

    public function test_guest_cannot_access_profile_page(): void
    {
        $response = $this->get('/my-profile');
        $response->assertRedirect('/login');
    }

    public function test_user_can_access_profile_page_with_data(): void
    {
        $response = $this->actingAs($this->user)->get('/my-profile');
        $response->assertStatus(200);
        $response->assertSee('john@example.com');
        $response->assertSee('John Doe');
    }

    public function test_user_can_update_profile_info_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyProfile::class)
            ->set('name', 'Jane Smith')
            ->set('email', 'jane@example.com')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertSee('Profil berhasil diperbarui.');

        $this->assertEquals('Jane Smith', $this->user->fresh()->name);
        $this->assertEquals('jane@example.com', $this->user->fresh()->email);
    }

    public function test_user_cannot_use_existing_email(): void
    {
        User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
        ]);

        Livewire::actingAs($this->user)
            ->test(MyProfile::class)
            ->set('email', 'other@example.com')
            ->call('saveProfile')
            ->assertHasErrors(['email']);
    }

    public function test_user_can_change_password_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyProfile::class)
            ->set('activeTab', 'security')
            ->set('current_password', 'oldpassword')
            ->set('new_password', 'newsecretpassword')
            ->set('new_password_confirmation', 'newsecretpassword')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSee('Password berhasil diperbarui.');

        $this->assertTrue(Hash::check('newsecretpassword', $this->user->fresh()->password));
    }

    public function test_wrong_current_password_fails(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyProfile::class)
            ->set('activeTab', 'security')
            ->set('current_password', 'wrongpassword')
            ->set('new_password', 'newsecretpassword')
            ->set('new_password_confirmation', 'newsecretpassword')
            ->call('savePassword')
            ->assertHasErrors(['current_password']);
    }
}
