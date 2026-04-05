<?php

namespace App\Http\Controllers;

use App\Models\WalletAccount;
use App\Models\WalletOauthGrant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class UserAgentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $personalWallet = WalletAccount::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereNull('company_id')
            ->first();

        $tokens = $user->tokens()
            ->with('client.company')
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        $grantsByToken = WalletOauthGrant::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereIn('oauth_access_token_id', $tokens->pluck('id'))
            ->get()
            ->keyBy('oauth_access_token_id');

        $agents = $tokens->map(function (Token $token) use ($grantsByToken, $personalWallet) {
            $grant = $grantsByToken->get($token->id);
            $wallet = $grant?->wallet_account_id !== null
                ? WalletAccount::query()
                    ->withoutGlobalScopes()
                    ->find($grant->wallet_account_id)
                : $personalWallet;

            $totalSpent = $wallet
                ? $wallet->ledgerEntries()
                    ->where('type', 'debit')
                    ->sum('amount_cents')
                : 0;

            return [
                'token_id' => $token->id,
                'client_name' => $token->client?->name ?? 'Unknown Agent',
                'company_name' => $token->client?->company?->name,
                'scopes' => $token->scopes ?? [],
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'wallet_public_id' => $wallet?->public_id,
                'wallet_balance_cents' => $wallet ? (int) $wallet->balance_cents : null,
                'wallet_status' => $wallet ? (string) $wallet->status : null,
                'total_spent_cents' => (int) $totalSpent,
            ];
        });

        return Inertia::render('user/agents/index', [
            'agents' => $agents,
        ]);
    }

    public function show(Request $request, string $tokenId): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $personalWallet = WalletAccount::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereNull('company_id')
            ->first();

        $token = $user->tokens()
            ->with('client.company')
            ->where('revoked', false)
            ->whereKey($tokenId)
            ->firstOrFail();

        $grant = WalletOauthGrant::query()
            ->withoutGlobalScopes()
            ->where('oauth_access_token_id', $token->id)
            ->where('user_id', $user->id)
            ->first();

        $wallet = $grant?->wallet_account_id !== null
            ? WalletAccount::query()
                ->withoutGlobalScopes()
                ->with('policy')
                ->find($grant->wallet_account_id)
            : $personalWallet?->load('policy');

        $ledgerEntries = $wallet
            ? $wallet->ledgerEntries()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'amount_cents' => (int) $entry->amount_cents,
                    'reference_type' => $entry->reference_type,
                    'description' => $entry->description,
                    'balance_after_cents' => (int) $entry->balance_after_cents,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
            : [];

        $policy = $wallet?->policy;

        return Inertia::render('user/agents/show', [
            'agent' => [
                'token_id' => $token->id,
                'client_name' => $token->client?->name ?? 'Unknown Agent',
                'company_name' => $token->client?->company?->name,
                'scopes' => $token->scopes ?? [],
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ],
            'wallet' => $wallet ? [
                'public_id' => $wallet->public_id,
                'balance_cents' => (int) $wallet->balance_cents,
                'status' => (string) $wallet->status,
                'environment' => $wallet->environment,
            ] : null,
            'policy' => $policy ? [
                'per_tx_limit_usd' => $policy->per_tx_limit_usd,
                'daily_spend_limit_usd' => $policy->daily_spend_limit_usd,
                'daily_tx_count' => $policy->daily_tx_count,
                'allowed_categories' => $policy->allowed_categories,
            ] : null,
            'ledgerEntries' => $ledgerEntries,
        ]);
    }
}
