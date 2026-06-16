<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminJournalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AdminJournalService $journalService,
    ) {}

    public function index(Request $request): View
    {
        $events = $this->journalService->paginate($request);
        $modules = config('admin_audit.modules', []);
        $sources = config('admin_audit.sources', []);

        $staffAdmins = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', [
                Role::SLUG_ADMIN,
                Role::SLUG_MANAGER,
                Role::SLUG_ANALYST,
            ]))
            ->orderBy('name')
            ->get(['id', 'name']);

        $permissionLabels = Permission::query()
            ->orderBy('name')
            ->pluck('name', 'slug');

        return view('admin.audit.index', compact(
            'events',
            'modules',
            'sources',
            'staffAdmins',
            'permissionLabels',
        ));
    }

    public function show(string $source, int $id): View
    {
        $event = $this->journalService->find($source, $id);
        abort_if($event === null, 404);

        $permissionLabels = Permission::query()
            ->orderBy('name')
            ->pluck('name', 'slug');

        return view('admin.audit.show', [
            'event' => $event,
            'permissionLabels' => $permissionLabels,
        ]);
    }
}
