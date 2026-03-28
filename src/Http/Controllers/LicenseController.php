<?php

namespace Edzero\LicenseClient\Http\Controllers;

use Edzero\LicenseClient\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use RuntimeException;

class LicenseController extends Controller
{
    public function show(LicenseService $licenseService): View|RedirectResponse
    {
        if (! $licenseService->shouldEnforce()) {
            return redirect()->to('/');
        }

        if ($licenseService->isVerified()) {
            return redirect()->to('/');
        }

        return view('license-client::activate');
    }

    public function activate(Request $request, LicenseService $licenseService): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'min:8', 'max:2048'],
        ]);

        try {
            $result = $licenseService->activate((string) $validated['license_key']);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['license_key' => $exception->getMessage()]);
        }

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['license_key' => $result['message']]);
        }

        return redirect('/')->with('status', $result['message']);
    }
}
