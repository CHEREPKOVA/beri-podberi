<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptEmployeeInvitationRequest;
use App\Models\UserInvitation;
use App\Services\CurrentRoleService;
use App\Services\DistributorEmployeeService;
use App\Services\EndCompanyEmployeeService;
use App\Services\ManufacturerEmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class EmployeeInvitationController extends Controller
{
    public function __construct(
        private ManufacturerEmployeeService $employees,
        private DistributorEmployeeService $distributorEmployees,
        private EndCompanyEmployeeService $endCompanyEmployees,
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        $invitation = UserInvitation::findByPlainToken($token)?->load(['manufacturerProfile', 'distributorProfile', 'endCompanyProfile']);

        if (! $invitation) {
            return view('auth.employee-invitation-invalid', [
                'message' => 'Ссылка приглашения не найдена.',
            ]);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('status', 'Доступ уже активирован. Войдите в личный кабинет.');
        }

        if ($invitation->isCancelled()) {
            return view('auth.employee-invitation-invalid', [
                'message' => 'Приглашение отменено. Обратитесь к администратору компании.',
            ]);
        }

        if ($invitation->isExpired()) {
            return view('auth.employee-invitation-invalid', [
                'message' => 'Срок действия ссылки истёк. Попросите администратора компании отправить приглашение повторно.',
            ]);
        }

        return view('auth.employee-invitation-register', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(AcceptEmployeeInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = UserInvitation::findByPlainToken($token);

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()
                ->route('employee-invitation.show', $token)
                ->withErrors(['token' => 'Ссылка приглашения недействительна или просрочена.']);
        }

        try {
            $user = match (true) {
                (bool) $invitation->end_company_profile_id => $this->endCompanyEmployees->accept($invitation, $request->validated('password')),
                (bool) $invitation->distributor_profile_id => $this->distributorEmployees->accept($invitation, $request->validated('password')),
                default => $this->employees->accept($invitation, $request->validated('password')),
            };
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $primaryRole = $user->activeRoles()->first();
        if ($primaryRole) {
            app(CurrentRoleService::class)->set($user, $primaryRole->id);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Доступ активирован. Добро пожаловать в личный кабинет.');
    }
}
