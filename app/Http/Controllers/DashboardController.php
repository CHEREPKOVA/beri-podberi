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

        $profileCompletion = match ($currentRole?->slug) {
            Role::SLUG_DISTRIBUTOR => $this->distributorCompletion->summary(
                $user->getOrCreateDistributorProfile()
            ),
            Role::SLUG_MANUFACTURER => $this->manufacturerCompletion->summary(
                $user->getOrCreateManufacturerProfile()
            ),
            Role::SLUG_END_COMPANY => $this->endCompanyCompletion->summary(
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
