<?php

namespace App\Services;

use App\Mail\CompanyInvitationMail;
use App\Models\CompanyInvitation;
use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerProfile;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CompanyInvitationService
{
    /**
     * @param  array<int, string>  $companyTypes
     */
    public function invite(
        User $inviter,
        string $email,
        string $companyName,
        array $companyTypes,
    ): CompanyInvitation {
        $email = Str::lower(trim($email));
        $companyName = trim($companyName);
        $companyTypes = $this->normalizeCompanyTypes($companyTypes);

        if ($companyTypes === []) {
            throw new InvalidArgumentException('Не выбрана роль компании.');
        }

        $this->assertCompanyNameAvailable($companyName);
        $this->assertEmailAvailable($email);

        return DB::transaction(function () use ($inviter, $email, $companyName, $companyTypes): CompanyInvitation {
            $user = User::create([
                'name' => $companyName,
                'email' => $email,
                'password' => Str::random(64),
                'is_active' => false,
            ]);

            $rolesBySlug = Role::query()
                ->whereIn('slug', $companyTypes)
                ->get()
                ->keyBy('slug');

            foreach ($companyTypes as $companyType) {
                $role = $rolesBySlug->get($companyType);
                if (! $role) {
                    continue;
                }

                $user->roles()->attach($role->id, [
                    'company_name' => $companyName,
                    'company_type' => $companyType,
                    'company_status' => CompanyStatus::AWAITING_CONFIRMATION,
                    'company_legal_name' => $companyName,
                    'company_contact_email' => $email,
                ]);
            }

            $plainToken = CompanyInvitation::createPlainToken();

            $invitation = CompanyInvitation::create([
                'email' => $email,
                'company_name' => $companyName,
                'company_types' => $companyTypes,
                'token' => CompanyInvitation::hashToken($plainToken),
                'inviter_id' => $inviter->id,
                'user_id' => $user->id,
                'expires_at' => now()->addDays(CompanyInvitation::TTL_DAYS),
                'sent_at' => now(),
            ]);

            $this->sendMail($invitation, $plainToken);

            return $invitation;
        });
    }

    public function resend(CompanyInvitation $invitation): CompanyInvitation
    {
        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException('Приглашение уже принято.');
        }

        $plainToken = $invitation->refreshTokenAndExpiry();
        $invitation->forceFill(['sent_at' => now()])->save();

        DB::table('role_user')
            ->where('user_id', $invitation->user_id)
            ->where('company_name', $invitation->company_name)
            ->update(['company_status' => CompanyStatus::AWAITING_CONFIRMATION]);

        $this->sendMail($invitation->fresh(), $plainToken);

        return $invitation->fresh();
    }

    public function cancel(CompanyInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException('Приглашение уже принято.');
        }

        // Токен не ротируем: старая ссылка должна открыть страницу «отменено».
        $invitation->forceFill([
            'cancelled_at' => now(),
        ])->save();
    }

    public function delete(CompanyInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $user = $invitation->user;
            $companyName = $invitation->company_name;
            $types = $invitation->company_types ?? [];

            $invitation->delete();

            if ($user) {
                $roleIds = Role::query()
                    ->whereIn('slug', array_merge($types, [Role::SLUG_COMPANY_EMPLOYEE]))
                    ->pluck('id');

                DB::table('role_user')
                    ->where('user_id', $user->id)
                    ->where('company_name', $companyName)
                    ->whereIn('role_id', $roleIds)
                    ->delete();

                if ($user->roles()->count() === 0) {
                    $user->delete();
                }
            }
        });
    }

    /**
     * @param  array{
     *     password: string,
     *     company_name: string,
     *     inn: string
     * }  $data
     */
    public function accept(CompanyInvitation $invitation, array $data): User
    {
        if (! $invitation->isValid()) {
            throw new InvalidArgumentException('Ссылка приглашения недействительна или просрочена.');
        }

        $companyName = trim($data['company_name']);
        $selectedTypes = $this->normalizeCompanyTypes($invitation->company_types ?? []);

        if ($selectedTypes === []) {
            throw new InvalidArgumentException('В приглашении не указана роль компании.');
        }

        return DB::transaction(function () use ($invitation, $data, $companyName, $selectedTypes): User {
            /** @var User $user */
            $user = User::query()->findOrFail($invitation->user_id);

            $oldCompanyName = $invitation->company_name;
            if ($companyName !== $oldCompanyName) {
                $this->assertCompanyNameAvailable($companyName, $user->id);
            }

            $moderationRequired = app(CompanyModerationService::class)->isRequired();
            $companyStatus = $moderationRequired ? CompanyStatus::PENDING : CompanyStatus::ACTIVE;

            $user->update([
                'name' => $companyName,
                'password' => $data['password'],
                'is_active' => ! $moderationRequired,
            ]);

            $rolesBySlug = Role::query()
                ->whereIn('slug', $selectedTypes)
                ->get()
                ->keyBy('slug');

            foreach ($selectedTypes as $type) {
                $role = $rolesBySlug->get($type);
                if (! $role) {
                    continue;
                }

                $user->roles()->updateExistingPivot($role->id, [
                    'company_name' => $companyName,
                    'company_type' => $type,
                    'company_status' => $companyStatus,
                    'company_legal_name' => $companyName,
                    'company_contact_email' => $invitation->email,
                ]);
            }

            $this->syncProfiles($user, $selectedTypes, $companyName, $data);

            $invitation->forceFill([
                'company_name' => $companyName,
                'company_types' => $selectedTypes,
                'accepted_at' => now(),
            ])->save();

            return $user->fresh(['roles']);
        });
    }

    public function findValidByToken(string $token): ?CompanyInvitation
    {
        $invitation = CompanyInvitation::findByPlainToken($token);

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    public function findPendingForCompany(string $companyName): ?CompanyInvitation
    {
        return CompanyInvitation::query()
            ->where('company_name', $companyName)
            ->whereNull('accepted_at')
            ->latest('id')
            ->first();
    }

    public function sendMail(CompanyInvitation $invitation, string $plainToken): void
    {
        Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $plainToken));
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, string>
     */
    public function normalizeCompanyTypes(array $types): array
    {
        $allowed = Role::corporateSlugsWithEmployees();
        $lookup = array_fill_keys($types, true);
        $normalized = [];

        foreach ($allowed as $slug) {
            if (isset($lookup[$slug])) {
                $normalized[] = $slug;
            }
        }

        return $normalized;
    }

    private function assertCompanyNameAvailable(string $companyName, ?int $exceptUserId = null): void
    {
        $query = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.company_name', $companyName)
            ->whereIn('roles.slug', Role::corporateSlugsWithEmployees());

        if ($exceptUserId !== null) {
            $query->where('role_user.user_id', '!=', $exceptUserId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Компания с таким наименованием уже существует.');
        }
    }

    private function assertEmailAvailable(string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException('Пользователь с этим email уже зарегистрирован.');
        }

        $pending = CompanyInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->exists();

        if ($pending) {
            throw new InvalidArgumentException('Для этого email уже есть активное приглашение.');
        }
    }

    /**
     * @param  array<int, string>  $selectedTypes
     * @param  array{inn: string}  $data
     */
    private function syncProfiles(User $user, array $selectedTypes, string $companyName, array $data): void
    {
        $profileData = [
            'full_name' => $companyName,
            'inn' => $data['inn'],
        ];

        if (in_array(Role::SLUG_MANUFACTURER, $selectedTypes, true)) {
            $profile = ManufacturerProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
            $this->syncPrimaryContact($profile->contacts(), $companyName, $user->email);
        }

        if (in_array(Role::SLUG_DISTRIBUTOR, $selectedTypes, true)) {
            $profile = DistributorProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
            $this->syncPrimaryContact($profile->contacts(), $companyName, $user->email);
        }

        if (in_array(Role::SLUG_END_COMPANY, $selectedTypes, true)) {
            $profile = EndCompanyProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
            $this->syncPrimaryContact($profile->contacts(), $companyName, $user->email);
        }
    }

    private function syncPrimaryContact($contactsRelation, string $fullName, string $email): void
    {
        $contact = $contactsRelation->where('is_primary', true)->first();
        $payload = [
            'full_name' => $fullName,
            'email' => $email,
            'is_primary' => true,
        ];

        if ($contact) {
            $contact->update($payload);

            return;
        }

        $contactsRelation->create($payload);
    }
}
