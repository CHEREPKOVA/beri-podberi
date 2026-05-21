<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_updates_audit_fields(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertSame('127.0.0.1', $user->last_login_ip);
        $this->assertNotEmpty($user->last_login_user_agent);
    }

    public function test_login_returns_general_error_message_without_field_binding(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123'),
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'Wrong123',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['auth'])
            ->assertSessionDoesntHaveErrors(['email', 'password']);
    }

    public function test_login_is_throttled_after_max_attempts(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'Wrong123',
            ]);
        }

        $this->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'Wrong123',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['auth', 'throttle_seconds']);
    }

    public function test_password_reset_requires_letters_and_numbers(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->from('/reset-password/'.$token.'?email='.$user->email)
            ->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'passwordonly',
                'password_confirmation' => 'passwordonly',
            ])
            ->assertRedirect('/reset-password/'.$token.'?email='.$user->email)
            ->assertSessionHasErrors(['password']);
    }

    public function test_password_reset_rejects_reused_passwords(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123'),
        ]);
        DB::table('user_password_histories')->insert([
            'user_id' => $user->id,
            'password_hash' => Hash::make('Oldpass123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = Password::broker()->createToken($user);

        $this->from('/reset-password/'.$token.'?email='.$user->email)
            ->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Oldpass123',
                'password_confirmation' => 'Oldpass123',
            ])
            ->assertRedirect('/reset-password/'.$token.'?email='.$user->email)
            ->assertSessionHasErrors(['password']);
    }
}
