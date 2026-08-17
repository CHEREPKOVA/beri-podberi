<?php

namespace Tests\Feature;

use App\Mail\CompanyApprovedMail;
use App\Mail\CompanyInvitationMail;
use App\Mail\CompanyRejectedMail;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CompanyStatus;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CompanyModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_registration_goes_to_pending_when_moderation_enabled(): void
    {
        Mail::fake();
        $this->enableModeration();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_DISTRIBUTOR],
                'full_name' => 'ООО На модерации',
                'email' => 'pending@example.com',
            ]);

        $invitation = CompanyInvitation::query()->where('email', 'pending@example.com')->firstOrFail();
        $plainToken = $this->plainTokenFor('pending@example.com');
        auth()->logout();

        $this->post(route('company-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'company_name' => 'ООО На модерации',
            'inn' => '7701234567',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();

        $user = User::query()->where('email', 'pending@example.com')->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'company_status' => CompanyStatus::PENDING,
        ]);

        $this->post('/login', [
            'email' => 'pending@example.com',
            'password' => 'Secret123',
        ])->assertSessionHasErrors(['auth']);
    }

    public function test_admin_can_approve_and_reject_registration(): void
    {
        Mail::fake();
        $this->enableModeration();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_MANUFACTURER],
                'full_name' => 'ООО Одобрение',
                'email' => 'approve@example.com',
            ]);

        $invitation = CompanyInvitation::query()->where('email', 'approve@example.com')->firstOrFail();
        $plainToken = $this->plainTokenFor('approve@example.com');
        auth()->logout();

        $this->post(route('company-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'company_name' => 'ООО Одобрение',
            'inn' => '7701234567',
        ]);

        $companyKey = rtrim(strtr(base64_encode(Role::SLUG_MANUFACTURER.'|ООО Одобрение'), '+/', '-_'), '=');

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.moderation.approve', $companyKey))
            ->assertRedirect();

        $user = User::query()->where('email', 'approve@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        Mail::assertSent(CompanyApprovedMail::class);

        // second company for reject flow
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.store'), [
                'company_types' => [Role::SLUG_DISTRIBUTOR],
                'full_name' => 'ООО Отклонение',
                'email' => 'reject@example.com',
            ]);

        $rejectInvitation = CompanyInvitation::query()->where('email', 'reject@example.com')->firstOrFail();
        $rejectPlainToken = $this->plainTokenFor('reject@example.com');
        auth()->logout();

        $this->post(route('company-invitation.accept', $rejectPlainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'company_name' => 'ООО Отклонение',
            'inn' => '7701234568',
        ]);

        $rejectKey = rtrim(strtr(base64_encode(Role::SLUG_DISTRIBUTOR.'|ООО Отклонение'), '+/', '-_'), '=');

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $admin->roles()->first()->id])
            ->post(route('admin.companies.moderation.reject', $rejectKey), [
                'reason' => 'Некорректные реквизиты',
            ])
            ->assertRedirect();

        $rejectedUser = User::query()->where('email', 'reject@example.com')->firstOrFail();
        $this->assertFalse($rejectedUser->is_active);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $rejectedUser->id,
            'company_status' => CompanyStatus::REJECTED,
        ]);
        Mail::assertSent(CompanyRejectedMail::class, function (CompanyRejectedMail $mail) {
            return $mail->reason === 'Некорректные реквизиты';
        });
    }

    private function enableModeration(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'registration.require_company_moderation'],
            [
                'group_key' => 'registration',
                'label' => 'Требуется модерация новых аккаунтов',
                'value' => '1',
                'value_type' => 'boolean',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
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

    private function makeAdmin(): User
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        return $admin;
    }
}
