<?php

namespace App\Http\Middleware;

use App\Services\AdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminAction
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request)) {
            return $response;
        }

        app(AdminAuditLogger::class)->logRequest($request, $response->getStatusCode());

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        $methods = config('admin_audit.methods', []);
        $method = strtoupper($request->method());
        $routeName = (string) ($request->route()?->getName() ?? '');

        return str_starts_with($routeName, 'admin.') && in_array($method, $methods, true);
    }
}
