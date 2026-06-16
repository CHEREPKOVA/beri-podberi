<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AdminAuditLogger
{
    public static function actionLabel(string $action): string
    {
        $labels = config('admin_audit.action_labels', []);

        return $labels[$action] ?? $labels['_default'] ?? 'Действие в панели управления';
    }

    public static function moduleLabelForAction(string $action): ?string
    {
        foreach (config('admin_audit.modules', []) as $module) {
            foreach ($module['prefixes'] ?? [] as $prefix) {
                if (str_starts_with($action, $prefix)) {
                    return $module['label'] ?? null;
                }
            }
        }

        return null;
    }

    public function logRequest(Request $request, int $responseStatus): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $route = $request->route();
        $routeName = $route?->getName() ?? 'admin.unknown';
        $routeParams = $route?->parameters() ?? [];
        [$companyType, $companyName] = $this->decodeCompanyContext($routeParams);

        $targetId = null;
        foreach ($routeParams as $value) {
            if (is_numeric($value)) {
                $targetId = (int) $value;
                break;
            }
        }

        $context = [
            'route' => $routeName,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_params' => $routeParams,
            'input' => $this->sanitizeInput($request->all()),
            'response_status' => $responseStatus,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ];

        DB::table('admin_action_logs')->insert([
            'admin_id' => $user->id,
            'admin_name' => $user->name,
            'action' => $routeName,
            'required_permission' => $this->resolveRequiredPermission($request),
            'target_type' => $this->resolveTargetType($routeParams),
            'target_id' => $targetId,
            'company_name' => $companyName,
            'company_type' => $companyType,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sanitizeInput(array $input): array
    {
        $hiddenKeys = config('admin_audit.hidden_input_keys', []);

        foreach ($hiddenKeys as $key) {
            if (Arr::has($input, $key)) {
                Arr::set($input, $key, '***');
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $routeParams
     */
    private function resolveTargetType(array $routeParams): ?string
    {
        foreach ($routeParams as $key => $_value) {
            if ($key === 'companyKey') {
                return 'company';
            }

            if (is_string($key) && str_ends_with($key, 'id')) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $routeParams
     */
    private function decodeCompanyContext(array $routeParams): array
    {
        $companyKey = $routeParams['companyKey'] ?? null;
        if (! is_string($companyKey) || $companyKey === '') {
            return [null, null];
        }

        $decoded = base64_decode(strtr($companyKey, '-_', '+/'), true);
        if (! is_string($decoded) || ! str_contains($decoded, '|')) {
            return [null, null];
        }

        [$companyType, $companyName] = explode('|', $decoded, 2);

        if ($companyName === '') {
            return [null, null];
        }

        return [$companyType, $companyName];
    }

    private function resolveRequiredPermission(Request $request): ?string
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        foreach ($middleware as $entry) {
            if (! is_string($entry) || ! str_starts_with($entry, 'permission:')) {
                continue;
            }

            $permissions = explode(',', substr($entry, strlen('permission:')));
            $permission = trim($permissions[0] ?? '');

            return $permission !== '' ? $permission : null;
        }

        return null;
    }
}
