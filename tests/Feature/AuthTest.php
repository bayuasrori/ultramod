<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\Models\PlatformLoginHistory;
use App\Platform\Models\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformRoleSeeder::class);
        RateLimiter::clear('test@example.com|127.0.0.1');
    }

    protected function user(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'platform_role_id' => PlatformRole::where('slug', 'member')->first()->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/platform')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_login_page_is_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = $this->user();

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/platform');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->user();

        $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->assertGuest();

        $this->assertDatabaseHas('platform_login_history', [
            'email' => 'test@example.com',
            'successful' => false,
        ]);
    }

    public function test_successful_login_is_recorded_in_history(): void
    {
        $user = $this->user();

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $this->assertDatabaseHas('platform_login_history', [
            'user_id' => $user->id,
            'email' => 'test@example.com',
            'successful' => true,
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->user();
        $user->update(['is_active' => false]);

        $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_logout(): void
    {
        $user = $this->user();

        $this->actingAs($user);
        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_registration_page_is_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create account');
    }

    public function test_first_registered_user_becomes_admin(): void
    {
        // hermetic: previous suites may leave users in the shared sqlite file
        User::query()->delete();

        $this->post('/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/platform');

        $user = User::where('email', 'first@example.com')->first();

        $this->assertTrue($user->isSuperAdmin());
        $this->assertSame('admin', $user->role->slug);
    }

    public function test_subsequent_registered_users_become_members(): void
    {
        $this->user();

        $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/platform');

        $user = User::where('email', 'second@example.com')->first();

        $this->assertFalse($user->isSuperAdmin());
        $this->assertSame('member', $user->role->slug);
    }

    public function test_registration_requires_unique_email(): void
    {
        $this->user();

        $this->from('/register')->post('/register', [
            'name' => 'Dup',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/register')->assertSessionHasErrors('email');
    }

    public function test_profile_page_requires_authentication(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_user_can_update_profile_name(): void
    {
        $user = $this->user();
        $user->password = 'secret123';

        $this->actingAs($user)->put('/profile', ['name' => 'Renamed'])
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Renamed',
        ]);
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertRedirect('/profile');

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'newsecret123',
        ])->assertRedirect('/platform');
    }

    public function test_login_history_page_shows_entries(): void
    {
        $user = $this->user();

        PlatformLoginHistory::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestAgent/1.0',
            'successful' => true,
            'login_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/profile/security')
            ->assertOk()
            ->assertSee('10.0.0.1');
    }
}
