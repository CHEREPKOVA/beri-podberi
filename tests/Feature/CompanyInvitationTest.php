<?php

namespace Tests\Feature;

use App\Mail\CompanyInvitationMail;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyStatus;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CompanyInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_send_company_invitation_without_password(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_DISTRIBUTOR],
                'full_name' => 'ООО Тест Дистрибьютор',
                'email' => 'invitee@example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'invitee@example.com',
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('role_user', [
            'company_name' => 'ООО Тест Дистрибьютор',
            'company_status' => CompanyStatus::AWAITING_CONFIRMATION,
        ]);

        $invitation = CompanyInvitation::query()->where('email', 'invitee@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertTrue($invitation->expires_at->greaterThan(now()->addDays(2)));
        $this->assertTrue($invitation->expires_at->lessThanOrEqualTo(now()->addDays(3)->addMinute()));
        $this->assertSame(64, strlen($invitation->token));
        $this->assertTrue(ctype_xdigit($invitation->token));

        Mail::assertSent(CompanyInvitationMail::class, function (CompanyInvitationMail $mail) use ($invitation) {
            return $mail->hasTo('invitee@example.com')
                && $mail->plainToken !== ''
                && $invitation->token === CompanyInvitation::hashToken($mail->plainToken);
        });
    }

    public function test_invitee_can_complete_registration_and_access_cabinet(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_MANUFACTURER],
                'full_name' => 'АО Производитель',
                'email' => 'manufacturer@example.com',
            ]);

        $invitation = CompanyInvitation::query()->where('email', 'manufacturer@example.com')->firstOrFail();
        $plainToken = $this->plainTokenFor('manufacturer@example.com');

        auth()->logout();

        $this->post(route('company-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'company_name' => 'АО Производитель',
            'inn' => '7701234567',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'manufacturer@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'company_status' => 'active',
        ]);
        $this->assertDatabaseHas('manufacturer_profiles', [
            'user_id' => $user->id,
            'inn' => '7701234567',
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_END_COMPANY],
                'full_name' => 'ООО Клиент',
                'email' => 'client@example.com',
            ]);

        $invitation = CompanyInvitation::query()->where('email', 'client@example.com')->firstOrFail();
        $plainToken = $this->plainTokenFor('client@example.com');
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        auth()->logout();

        $this->get(route('company-invitation.show', $plainToken))
            ->assertOk()
            ->assertSee('Срок действия ссылки истёк');

        $this->post(route('company-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'company_name' => 'ООО Клиент',
            'inn' => '7701234567',
        ])->assertRedirect();

        $this->assertGuest();
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_admin_can_resend_and_cancel_invitation(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_DISTRIBUTOR],
                'full_name' => 'ООО Ресенд',
                'email' => 'resend@example.com',
            ]);

        $invitation = CompanyInvitation::query()->where('email', 'resend@example.com')->firstOrFail();
        $oldTokenHash = $invitation->token;
        $oldPlainToken = $this->plainTokenFor('resend@example.com');
        $companyKey = rtrim(strtr(base64_encode(Role::SLUG_DISTRIBUTOR.'|ООО Ресенд'), '+/', '-_'), '=');

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.invitation.resend', $companyKey))
            ->assertRedirect();

        $invitation->refresh();
        $this->assertNotSame($oldTokenHash, $invitation->token);
        Mail::assertSent(CompanyInvitationMail::class, 2);

        $newPlainToken = $this->latestPlainTokenFor('resend@example.com');

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.invitation.cancel', $companyKey))
            ->assertRedirect();

        $this->assertNotNull($invitation->fresh()->cancelled_at);
        $this->get(route('company-invitation.show', $newPlainToken))
            ->assertOk()
            ->assertSee('Приглашение отменено');

        $this->get(route('company-invitation.show', $oldPlainToken))
            ->assertOk()
            ->assertSee('Ссылка приглашения не найдена');
    }

    private function plainTokenFor(string $email): string
    {
        $plainToken = null;

        Mail::assertSent(CompanyInvitationMail::class, function (CompanyInvitationMail $mail) use ($email, &$plainToken) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $plainToken = $mail->plainToken;

            return true;
        });

        $this->assertNotNull($plainToken);

        return $plainToken;
    }

    private function latestPlainTokenFor(string $email): string
    {
        $mails = Mail::sent(CompanyInvitationMail::class, fn (CompanyInvitationMail $mail) => $mail->hasTo($email));
        $this->assertNotEmpty($mails);

        return collect($mails)->last()->plainToken;
    }

    private function makeAdmin(): User
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        return $admin;
    }
}
