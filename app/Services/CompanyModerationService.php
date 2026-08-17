<?php

namespace App\Services;

use App\Mail\CompanyApprovedMail;
use App\Mail\CompanyRejectedMail;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CompanyStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class CompanyModerationService
{
    public function isRequired(): bool
    {
        return (bool) SystemSetting::getActiveParsed('registration.require_company_moderation', false);
    }

    public function approve(string $companyName): void
    {
        $owner = $this->findCompanyOwner($companyName);
        if (! $owner) {
            throw new InvalidArgumentException('Пользователь компании не найден.');
        }

        $status = $this->companyStatus($companyName);
        if (! in_array($status, [CompanyStatus::PENDING, CompanyStatus::REJECTED], true)) {
            throw new InvalidArgumentException('Компания не ожидает модерации.');
        }

        DB::transaction(function () use ($companyName, $owner): void {
            $this->updateCompanyPivots($companyName, [
                'company_status' => CompanyStatus::ACTIVE,
            ], clearRejectReason: true);

            $owner->update(['is_active' => true]);
        });

        Mail::to($owner->email)->send(new CompanyApprovedMail($companyName));
    }

    public function reject(string $companyName, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Укажите причину отклонения.');
        }

        $owner = $this->findCompanyOwner($companyName);
        if (! $owner) {
            throw new InvalidArgumentException('Пользователь компании не найден.');
        }

        $status = $this->companyStatus($companyName);
        if ($status !== CompanyStatus::PENDING) {
            throw new InvalidArgumentException('Отклонить можно только компанию, ожидающую одобрения.');
        }

        DB::transaction(function () use ($companyName, $owner, $reason): void {
            $this->updateCompanyPivots($companyName, [
                'company_status' => CompanyStatus::REJECTED,
            ], rejectReason: $reason);

            $owner->update(['is_active' => false]);
        });

        Mail::to($owner->email)->send(new CompanyRejectedMail($companyName, $reason));
    }

    public function findCompanyOwner(string $companyName): ?User
    {
        return User::query()
            ->whereHas('roles', function ($q) use ($companyName): void {
                $q->where('role_user.company_name', $companyName)
                    ->whereIn('roles.slug', Role::corporateSlugsWithEmployees());
            })
            ->orderBy('id')
            ->first();
    }

    public function companyStatus(string $companyName): ?string
    {
        $status = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.company_name', $companyName)
            ->whereIn('roles.slug', Role::corporateSlugsWithEmployees())
            ->value('role_user.company_status');

        return $status !== null ? (string) $status : null;
    }

    public function rejectReason(string $companyName): ?string
    {
        $params = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.company_name', $companyName)
            ->whereIn('roles.slug', Role::corporateSlugsWithEmployees())
            ->whereNotNull('role_user.company_params')
            ->value('role_user.company_params');

        if (! is_string($params) || $params === '') {
            return null;
        }

        $decoded = json_decode($params, true);
        if (! is_array($decoded)) {
            return null;
        }

        $reason = $decoded['moderation_reject_reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateCompanyPivots(
        string $companyName,
        array $attributes,
        bool $clearRejectReason = false,
        ?string $rejectReason = null,
    ): void {
        $roleIds = Role::query()
            ->whereIn('slug', array_merge(Role::corporateSlugsWithEmployees(), [Role::SLUG_COMPANY_EMPLOYEE]))
            ->pluck('id');

        $rows = DB::table('role_user')
            ->where('company_name', $companyName)
            ->whereIn('role_id', $roleIds)
            ->get(['id', 'company_params']);

        foreach ($rows as $row) {
            $params = [];
            if (is_string($row->company_params) && $row->company_params !== '') {
                $decoded = json_decode($row->company_params, true);
                if (is_array($decoded)) {
                    $params = $decoded;
                }
            }

            if ($clearRejectReason) {
                unset($params['moderation_reject_reason']);
            }

            if ($rejectReason !== null) {
                $params['moderation_reject_reason'] = $rejectReason;
            }

            $update = $attributes;
            $update['company_params'] = $params === [] ? null : json_encode($params, JSON_UNESCAPED_UNICODE);
            $update['updated_at'] = now();

            DB::table('role_user')->where('id', $row->id)->update($update);
        }
    }
}
