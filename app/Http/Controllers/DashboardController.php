<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\DistributorProfileCompletionService;
use App\Services\EndCompanyProfileCompletionService;
use App\Services\ManufacturerProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DistributorProfileCompletionService $distributorCompletion,
        private readonly ManufacturerProfileCompletionService $manufacturerCompletion,
        private readonly EndCompanyProfileCompletionService $endCompanyCompletion,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $currentRole = $user->getCurrentRole();
        $rolePivot = $user->roles->firstWhere('id', $currentRole?->id);
        $companyName = $rolePivot?->pivot?->company_name ?? null;
        $roleDisplay = $currentRole ? $currentRole->labelWithCompany($companyName) : '—';

        $profileCompletion = match (true) {
            $currentRole?->slug === Role::SLUG_DISTRIBUTOR,
            $user->isDistributorStaff() => $this->distributorCompletion->summary(
                $user->getOrCreateDistributorProfile()
            ),
            $currentRole?->slug === Role::SLUG_MANUFACTURER,
            $user->isManufacturerStaff() => $this->manufacturerCompletion->summary(
                $user->getOrCreateManufacturerProfile()
            ),
            $currentRole?->slug === Role::SLUG_END_COMPANY,
            $user->isEndCompanyStaff() => $this->endCompanyCompletion->summary(
                $user->getOrCreateEndCompanyProfile()
            ),
            default => null,
        };

        return view('dashboard', compact(
            'currentRole',
            'roleDisplay',
            'profileCompletion',
        ));
    }
}
