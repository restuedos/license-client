<?php

namespace Edzero\LicenseClient\Http\Middleware;

use Closure;
use Edzero\LicenseClient\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseIsValid
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        /** @var LicenseService $licenseService */
        $licenseService = app(LicenseService::class);

        if ($licenseService->isVerified()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'message' => 'Aplikasi belum diaktivasi. Silakan verifikasi license key terlebih dahulu.',
            ], 423);
        }

        return redirect()->route('license-client.activate.show');
    }

    private function shouldBypass(Request $request): bool
    {
        /** @var LicenseService $license */
        $license = app(LicenseService::class);
        if (! $license->shouldEnforce()) {
            return true;
        }

        // Path-based: this middleware is appended globally and runs before route resolution.
        return $request->is('license/activate')
            || $request->is('up')
            || $request->is('api/health')
            || $request->is('api/version');
    }
}
