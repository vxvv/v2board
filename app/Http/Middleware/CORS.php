<?php

namespace App\Http\Middleware;

use Closure;

class CORS
{
    public function handle($request, Closure $next)
    {
        $origin = $request->header('origin');

        $allowedOrigin = $this->getAllowedOrigin($origin);
        $response = $next($request);

        if ($allowedOrigin) {
            $response->header('Access-Control-Allow-Origin', $allowedOrigin);
            $response->header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS,HEAD');
            $response->header('Access-Control-Allow-Headers', 'Origin,Content-Type,Accept,Authorization,X-Request-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', 10080);
        }

        return $response;
    }

    private function getAllowedOrigin(?string $origin): ?string
    {
        if (empty($origin)) {
            return null;
        }

        $appUrl = config('v2board.app_url');
        if ($appUrl) {
            $parsed = parse_url($appUrl);
            $allowed = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            if (isset($parsed['port'])) {
                $allowed .= ':' . $parsed['port'];
            }
            if (strcasecmp(rtrim($origin, '/'), rtrim($allowed, '/')) === 0) {
                return rtrim($origin, '/');
            }
            return null;
        }

        return rtrim($origin, '/');
    }
}
