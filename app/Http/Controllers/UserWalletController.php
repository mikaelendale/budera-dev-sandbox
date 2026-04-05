<?php

namespace App\Http\Controllers;

use App\Models\BankLink;
use App\Models\WalletAccount;
use App\Models\WalletOauthGrant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class UserWalletController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $wallet = WalletAccount::query()
            ->withoutGlobalScopes()
            ->with(['bankLinks' => fn ($query) => $query->withoutGlobalScopes()->orderByDesc('id')])
            ->where('user_id', $user->getKey())
            ->whereNull('company_id')
            ->first();

        $tokens = $user->tokens()
            ->with(['client.company'])
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        $grants = WalletOauthGrant::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->whereIn('oauth_access_token_id', $tokens->pluck('id'))
            ->get()
            ->keyBy('oauth_access_token_id');

        $walletId = $wallet?->getKey();

        $connections = $tokens->map(function (Token $token) use ($grants, $walletId): array {
            $grant = $grants->get($token->id);
            $grantWalletId = $grant?->wallet_account_id !== null ? (int) $grant->wallet_account_id : null;
            $hasWalletAccess = $walletId !== null && ($grantWalletId === null || $grantWalletId === $walletId);

            return [
                'token_id' => $token->id,
                'client_name' => $token->client?->name ?? 'Unknown integration',
                'company_name' => $token->client?->company?->name,
                'scopes' => $token->scopes ?? [],
                'authorized_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'has_wallet_access' => $hasWalletAccess,
            ];
        });

        $walletConnections = $connections
            ->filter(fn (array $connection): bool => $connection['has_wallet_access'])
            ->values();

        $companyAccess = $walletConnections
            ->groupBy(fn (array $connection): string => (string) ($connection['company_name'] ?? $connection['client_name']))
            ->map(function ($items, string $companyName): array {
                $latestAuthorizedAt = collect($items)
                    ->pluck('authorized_at')
                    ->filter()
                    ->sortDesc()
                    ->first();

                return [
                    'company_name' => $companyName !== '' ? $companyName : 'Independent integration',
                    'connection_count' => count($items),
                    'scopes' => collect($items)
                        ->pluck('scopes')
                        ->flatten()
                        ->unique()
                        ->values()
                        ->all(),
                    'latest_authorized_at' => $latestAuthorizedAt,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('user/wallet/index', [
            'wallet' => $wallet === null ? null : [
                'public_id' => $wallet->public_id,
                'balance_cents' => (int) $wallet->balance_cents,
                'status' => (string) $wallet->status,
                'environment' => $wallet->environment,
                'partner_account_id' => $wallet->partner_account_id,
                'bank_links' => $wallet->bankLinks
                    ->map(fn (BankLink $link): array => [
                        'id' => $link->public_id,
                        'bank_slug' => $link->bank_slug,
                        'status' => (string) $link->status,
                        'account_last4' => $link->account_last4,
                        'verified_at' => $link->verified_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
            'companyAccess' => $companyAccess,
            'connections' => $walletConnections->all(),
        ]);
    }
}
