<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptCompanyInvitationRequest;
use App\Models\CompanyInvitation;
use App\Services\AdminAuditLogger;
use App\Services\CompanyInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class CompanyInvitationController extends Controller
{
    public function __construct(private CompanyInvitationService $invitations) {}

    public function show(string $token): View|RedirectResponse
    {
        $invitation = CompanyInvitation::findByPlainToken($token);

        if (! $invitation) {
            return view('auth.company-invitation-invalid', [
                'message' => 'Ссылка приглашения не найдена.',
            ]);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('status', 'Регистрация уже завершена. Войдите в личный кабинет.');
        }

        if ($invitation->isCancelled()) {
            return view('auth.company-invitation-invalid', [
                'message' => 'Приглашение отменено. Обратитесь к администратору платформы.',
            ]);
        }

        if ($invitation->isExpired()) {
            return view('auth.company-invitation-invalid', [
                'message' => 'Срок действия ссылки истёк (3 суток). Попросите администратора отправить приглашение повторно.',
            ]);
        }

        return view('auth.company-invitation-register', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(AcceptCompanyInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findValidByToken($token);

        if (! $invitation) {
            return redirect()
                ->route('company-invitation.show', $token)
                ->withErrors(['token' => 'Ссылка приглашения недействительна или просрочена.']);
        }

        try {
            $user = $this->invitations->accept($invitation, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['company_name' => $e->getMessage()]);
        }

        app(AdminAuditLogger::class)->logSecurityEvent(
            'security.company_invitation.accepted',
            $user,
            $invitation->company_name,
            is_array($invitation->company_types) ? ($invitation->company_types[0] ?? null) : null,
            'company_invitation',
            $invitation->id,
            [
                'email' => $invitation->email,
                'company_types' => $invitation->company_types,
                'needs_moderation' => ! $user->is_active,
            ],
        );

        $needsModeration = ! $user->is_active;

        if ($needsModeration) {
            return redirect()->route('login')->with(
                'status',
                'Регистрация завершена. Аккаунт ожидает одобрения администратором. После активации вы сможете войти в личный кабинет.'
            );
        }

        Auth::login($user);
        $request->session()->regenerate();

        $primaryRole = $user->activeRoles()->first();
        if ($primaryRole) {
            $user->setCurrentRole($primaryRole->id);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Регистрация завершена. Добро пожаловать в личный кабинет.');
    }
}
