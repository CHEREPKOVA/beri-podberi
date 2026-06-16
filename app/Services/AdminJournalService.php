<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminJournalService
{
    private const FETCH_LIMIT = 500;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(Request $request): Collection
    {
        $sources = $this->resolvedSources($request);

        $events = collect();

        if (in_array('admin_action', $sources, true)) {
            $events = $events->merge($this->collectAdminActions($request));
        }

        if (in_array('partnership', $sources, true)) {
            $events = $events->merge($this->collectPartnershipLogs($request));
        }

        if (in_array('distributor_product', $sources, true)) {
            $events = $events->merge($this->collectDistributorProductLogs($request));
        }

        return $events
            ->sortByDesc(fn (array $event) => $event['occurred_at'])
            ->values();
    }

    public function paginate(Request $request, int $perPage = 30): LengthAwarePaginator
    {
        $events = $this->collect($request);
        $page = Paginator::resolveCurrentPage();
        $total = $events->count();

        $items = $events
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    public function paginateForCompany(string $companyName, string $companyType, int $perPage = 20): LengthAwarePaginator
    {
        $request = Request::create('/', 'GET', [
            'company_name' => $companyName,
        ]);

        return $this->paginate($request, $perPage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $source, int $id): ?array
    {
        return match ($source) {
            'admin_action' => $this->findAdminAction($id),
            'partnership' => $this->findPartnershipLog($id),
            'distributor_product' => $this->findDistributorProductLog($id),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function resolvedSources(Request $request): array
    {
        $all = array_keys(config('admin_audit.sources', []));

        if ($request->filled('source') && in_array($request->source, $all, true)) {
            return [$request->source];
        }

        if ($request->filled('module')) {
            $module = config('admin_audit.modules.'.$request->module);
            if (is_array($module) && ! empty($module['sources'])) {
                return $module['sources'];
            }
        }

        return $all;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectAdminActions(Request $request): Collection
    {
        return $this->adminActionQuery($request)
            ->orderByDesc('admin_action_logs.id')
            ->limit(self::FETCH_LIMIT)
            ->get()
            ->map(fn (object $row) => $this->normalizeAdminAction($row));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectPartnershipLogs(Request $request): Collection
    {
        return $this->partnershipQuery($request)
            ->orderByDesc('logs.id')
            ->limit(self::FETCH_LIMIT)
            ->get()
            ->map(fn (object $row) => $this->normalizePartnershipLog($row));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectDistributorProductLogs(Request $request): Collection
    {
        return $this->distributorProductQuery($request)
            ->orderByDesc('logs.id')
            ->limit(self::FETCH_LIMIT)
            ->get()
            ->map(fn (object $row) => $this->normalizeDistributorProductLog($row));
    }

    private function adminActionQuery(Request $request): Builder
    {
        $query = DB::table('admin_action_logs')
            ->leftJoin('users', 'users.id', '=', 'admin_action_logs.admin_id')
            ->select([
                'admin_action_logs.*',
                DB::raw("COALESCE(users.name, admin_action_logs.admin_name, 'Удалённый пользователь') as actor_display_name"),
            ]);

        $this->applySharedFilters($query, $request, 'admin_action_logs', 'admin_id');

        if ($request->filled('company_name')) {
            $query->where('admin_action_logs.company_name', 'like', '%'.$request->company_name.'%');
        }

        if ($request->filled('company_type')) {
            $query->where('admin_action_logs.company_type', $request->company_type);
        }

        if ($request->filled('module')) {
            $prefixes = config('admin_audit.modules.'.$request->module.'.prefixes', []);
            if ($prefixes !== []) {
                $query->where(function (Builder $inner) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('admin_action_logs.action', 'like', $prefix.'%');
                    }
                });
            }
        }

        if ($request->filled('action')) {
            $query->where('admin_action_logs.action', $request->action);
        }

        if ($request->filled('status')) {
            if ($request->status === 'success') {
                $query->where('admin_action_logs.context->response_status', '<', 400);
            } elseif ($request->status === 'failed') {
                $query->where('admin_action_logs.context->response_status', '>=', 400);
            }
        }

        return $query;
    }

    private function partnershipQuery(Request $request): Builder
    {
        $query = DB::table('manufacturer_distributor_partnership_logs as logs')
            ->leftJoin('users', 'users.id', '=', 'logs.performed_by_user_id')
            ->join('manufacturer_profiles as manufacturer', 'manufacturer.id', '=', 'logs.manufacturer_profile_id')
            ->join('distributor_profiles as distributor', 'distributor.id', '=', 'logs.distributor_profile_id')
            ->select([
                'logs.*',
                'users.name as actor_display_name',
                'manufacturer.full_name as manufacturer_name',
                'distributor.full_name as distributor_name',
            ]);

        $this->applySharedFilters($query, $request, 'logs', 'performed_by_user_id');

        if ($request->filled('company_name')) {
            $companyName = '%'.$request->company_name.'%';
            $query->where(function (Builder $inner) use ($companyName): void {
                $inner->where('manufacturer.full_name', 'like', $companyName)
                    ->orWhere('distributor.full_name', 'like', $companyName);
            });
        }

        return $query;
    }

    private function distributorProductQuery(Request $request): Builder
    {
        $query = DB::table('distributor_product_change_logs as logs')
            ->leftJoin('users', 'users.id', '=', 'logs.performed_by_user_id')
            ->join('distributor_products as product', 'product.id', '=', 'logs.distributor_product_id')
            ->join('distributor_profiles as distributor', 'distributor.id', '=', 'product.distributor_profile_id')
            ->select([
                'logs.*',
                'users.name as actor_display_name',
                'distributor.full_name as distributor_name',
                'product.name as product_name',
            ]);

        $this->applySharedFilters($query, $request, 'logs', 'performed_by_user_id');

        if ($request->filled('company_name')) {
            $query->where('distributor.full_name', 'like', '%'.$request->company_name.'%');
        }

        return $query;
    }

    private function applySharedFilters(Builder $query, Request $request, string $table, string $actorColumn): void
    {
        if ($request->filled('admin_id')) {
            $query->where($table.'.'.$actorColumn, (int) $request->admin_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate($table.'.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate($table.'.created_at', '<=', $request->date_to);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAdminAction(object $row): array
    {
        $context = json_decode((string) ($row->context ?? '{}'), true) ?: [];

        return [
            'key' => 'admin_action:'.$row->id,
            'source' => 'admin_action',
            'source_id' => (int) $row->id,
            'title' => AdminAuditLogger::actionLabel((string) $row->action),
            'subtitle' => null,
            'actor_id' => $row->admin_id ? (int) $row->admin_id : null,
            'actor_name' => (string) $row->actor_display_name,
            'company_name' => $row->company_name,
            'company_type' => $row->company_type,
            'module' => AdminAuditLogger::moduleLabelForAction((string) $row->action),
            'module_key' => $this->moduleKeyForAction((string) $row->action),
            'source_label' => config('admin_audit.sources.admin_action.label'),
            'required_permission' => $row->required_permission,
            'response_status' => (int) ($context['response_status'] ?? 0),
            'occurred_at' => Carbon::parse($row->created_at)->timestamp,
            'occurred_at_label' => Carbon::parse($row->created_at)->format('d.m.Y H:i'),
            'payload' => [
                'action' => $row->action,
                'target_type' => $row->target_type,
                'target_id' => $row->target_id,
                'context' => $context,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePartnershipLog(object $row): array
    {
        $labels = config('admin_audit.partnership_action_labels', []);

        return [
            'key' => 'partnership:'.$row->id,
            'source' => 'partnership',
            'source_id' => (int) $row->id,
            'title' => $labels[$row->action] ?? (string) $row->action,
            'subtitle' => $row->description,
            'actor_id' => $row->performed_by_user_id ? (int) $row->performed_by_user_id : null,
            'actor_name' => (string) ($row->actor_display_name ?? 'Система'),
            'company_name' => trim($row->manufacturer_name.' ↔ '.$row->distributor_name),
            'company_type' => null,
            'module' => config('admin_audit.modules.partnership.label'),
            'module_key' => 'partnership',
            'source_label' => config('admin_audit.sources.partnership.label'),
            'required_permission' => null,
            'response_status' => 200,
            'occurred_at' => Carbon::parse($row->created_at)->timestamp,
            'occurred_at_label' => Carbon::parse($row->created_at)->format('d.m.Y H:i'),
            'payload' => [
                'action' => $row->action,
                'description' => $row->description,
                'manufacturer_name' => $row->manufacturer_name,
                'distributor_name' => $row->distributor_name,
                'meta' => json_decode((string) ($row->meta ?? 'null'), true),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDistributorProductLog(object $row): array
    {
        $labels = config('admin_audit.distributor_product_action_labels', []);

        return [
            'key' => 'distributor_product:'.$row->id,
            'source' => 'distributor_product',
            'source_id' => (int) $row->id,
            'title' => $labels[$row->action] ?? (string) $row->action,
            'subtitle' => $row->description ?: $row->product_name,
            'actor_id' => $row->performed_by_user_id ? (int) $row->performed_by_user_id : null,
            'actor_name' => (string) ($row->actor_display_name ?? 'Система'),
            'company_name' => $row->distributor_name,
            'company_type' => null,
            'module' => config('admin_audit.modules.distributor_product.label'),
            'module_key' => 'distributor_product',
            'source_label' => config('admin_audit.sources.distributor_product.label'),
            'required_permission' => null,
            'response_status' => 200,
            'occurred_at' => Carbon::parse($row->created_at)->timestamp,
            'occurred_at_label' => Carbon::parse($row->created_at)->format('d.m.Y H:i'),
            'payload' => [
                'action' => $row->action,
                'description' => $row->description,
                'product_name' => $row->product_name,
                'distributor_name' => $row->distributor_name,
                'meta' => json_decode((string) ($row->meta ?? 'null'), true),
            ],
        ];
    }

    private function moduleKeyForAction(string $action): ?string
    {
        foreach (config('admin_audit.modules', []) as $key => $module) {
            foreach ($module['prefixes'] ?? [] as $prefix) {
                if (str_starts_with($action, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAdminAction(int $id): ?array
    {
        $row = DB::table('admin_action_logs')
            ->leftJoin('users', 'users.id', '=', 'admin_action_logs.admin_id')
            ->select([
                'admin_action_logs.*',
                DB::raw("COALESCE(users.name, admin_action_logs.admin_name, 'Удалённый пользователь') as actor_display_name"),
            ])
            ->where('admin_action_logs.id', $id)
            ->first();

        return $row ? $this->normalizeAdminAction($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPartnershipLog(int $id): ?array
    {
        $row = $this->partnershipQuery(Request::create('/'))
            ->where('logs.id', $id)
            ->first();

        return $row ? $this->normalizePartnershipLog($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDistributorProductLog(int $id): ?array
    {
        $row = $this->distributorProductQuery(Request::create('/'))
            ->where('logs.id', $id)
            ->first();

        return $row ? $this->normalizeDistributorProductLog($row) : null;
    }
}
