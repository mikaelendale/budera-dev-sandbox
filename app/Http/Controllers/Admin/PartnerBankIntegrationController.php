<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerBankIntegration;
use App\Services\Banking\MockBankClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerBankIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $integrations = PartnerBankIntegration::query()
            ->orderBy('provider')
            ->orderBy('environment')
            ->get()
            ->map(fn (PartnerBankIntegration $i) => $i->safeForInertia());

        return Inertia::render('admin/partner-banks', [
            'integrations' => $integrations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:64'],
            'environment' => ['required', 'in:sandbox,live'],
            'base_url' => ['nullable', 'string', 'max:2048'],
            'outbound_api_secret' => ['nullable', 'string', 'max:8192'],
            'inbound_webhook_secret' => ['nullable', 'string', 'max:8192'],
        ]);

        $baseUrl = $validated['base_url'] ?? null;
        if ($baseUrl === '') {
            $baseUrl = null;
        }

        PartnerBankIntegration::query()->create([
            'label' => $validated['label'],
            'provider' => $validated['provider'],
            'environment' => $validated['environment'],
            'base_url' => $baseUrl,
            'credentials' => [
                'outbound_api_secret' => $validated['outbound_api_secret'] ?? '',
                'inbound_webhook_secret' => $validated['inbound_webhook_secret'] ?? '',
            ],
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', __('Partner bank integration saved.'));
    }

    public function update(Request $request, PartnerBankIntegration $integration): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'environment' => [
                'sometimes',
                'in:sandbox,live',
                Rule::unique('partner_bank_integrations', 'environment')
                    ->where('provider', $integration->provider)
                    ->ignore($integration->getKey()),
            ],
            'base_url' => ['nullable', 'string', 'max:2048'],
            'outbound_api_secret' => ['nullable', 'string', 'max:8192'],
            'inbound_webhook_secret' => ['nullable', 'string', 'max:8192'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $creds = is_array($integration->credentials) ? $integration->credentials : [];

        if (array_key_exists('outbound_api_secret', $validated)) {
            $v = $validated['outbound_api_secret'];
            if ($v !== null && $v !== '') {
                $creds['outbound_api_secret'] = $v;
            }
        }

        if (array_key_exists('inbound_webhook_secret', $validated)) {
            $v = $validated['inbound_webhook_secret'];
            if ($v !== null && $v !== '') {
                $creds['inbound_webhook_secret'] = $v;
            }
        }

        $baseUrl = $integration->base_url;
        if (array_key_exists('base_url', $validated)) {
            $raw = $validated['base_url'];
            $baseUrl = ($raw !== null && $raw !== '') ? $raw : null;
        }

        $integration->fill([
            'label' => $validated['label'] ?? $integration->label,
            'environment' => $validated['environment'] ?? $integration->environment,
            'base_url' => $baseUrl,
            'credentials' => $creds,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $integration->is_active,
        ]);
        $integration->save();

        return redirect()->back()->with('status', __('Partner bank integration updated.'));
    }

    public function destroy(PartnerBankIntegration $integration): RedirectResponse
    {
        $integration->delete();

        return redirect()->back()->with('status', __('Partner bank integration removed.'));
    }

    public function test(Request $request, PartnerBankIntegration $integration): RedirectResponse
    {
        $provider = $integration->provider;
        $baseUrl = rtrim((string) ($integration->base_url ?? ''), '/');
        $creds = is_array($integration->credentials) ? $integration->credentials : [];

        if ($provider === 'mock_bank') {
            $secret = $creds['outbound_api_secret'] ?? null;
            $secret = is_string($secret) && $secret !== '' ? $secret : null;

            if ($baseUrl === '' || $secret === null) {
                return redirect()->route('admin.partner-banks.index')->with('error', 'Cannot test: missing base_url or outbound_api_secret.');
            }

            try {
                $client = new MockBankClient($baseUrl, $secret);
                $health = $client->health();

                return redirect()->route('admin.partner-banks.index')->with('status', 'Health OK: '.json_encode($health));
            } catch (\Throwable $e) {
                return redirect()->route('admin.partner-banks.index')->with('error', 'Health failed: '.$e->getMessage());
            }
        }

        return redirect()->route('admin.partner-banks.index')->with('error', 'Provider not supported for test: '.$provider);
    }
}
