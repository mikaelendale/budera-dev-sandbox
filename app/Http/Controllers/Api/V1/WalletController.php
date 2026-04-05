<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\WalletAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $access = $user->currentAccessToken();

        $company = $user->firstCompany();
        $wallet = null;
        if ($company !== null) {
            $account = WalletAccount::query()
                ->where('company_id', $company->getKey())
                ->orderByDesc('id')
                ->first();
            if ($account !== null) {
                $wallet = [
                    'id' => $account->public_id,
                    'status' => $account->status->getValue(),
                    'environment' => $account->environment,
                    'balance_usd' => $account->balanceUsd(),
                    'agent_id' => $account->agent_id,
                ];
            }
        }

        $payload = [
            'wallet' => $wallet ?? [
                'id' => 'sandbox',
                'label' => 'Sandbox wallet',
            ],
            'scopes' => $access?->scopes ?? [],
        ];

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        if (! $apiKey instanceof ApiKey) {
            $apiKey = Auth::guard('api-key')->currentApiKey();
        }

        if ($apiKey instanceof ApiKey && $company !== null) {
            $payload['environment'] = $apiKey->environment;
            $payload['company'] = [
                'id' => (string) $company->getKey(),
                'name' => $company->name,
                'logo_url' => $company->logo_url,
            ];
        }

        if ($access !== null) {
            $payload['token_expires_at'] = $access->expires_at?->toIso8601String();
            if ($company !== null) {
                $payload['linked_accounts'] = BankLink::query()
                    ->where('user_id', $user->getKey())
                    ->where('company_id', $company->getKey())
                    ->orderByDesc('id')
                    ->limit(25)
                    ->get()
                    ->map(fn (BankLink $link): array => [
                        'id' => $link->public_id,
                        'status' => $link->status->getValue(),
                        'account_last4' => $link->account_last4,
                    ])
                    ->values()
                    ->all();
            }
        }

        return response()->json($payload);
    }
}
