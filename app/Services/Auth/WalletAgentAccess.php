<?php

namespace App\Services\Auth;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\WalletAccount;
use App\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

/**
 * Enforces agent scoping on wallet accounts via {@see WalletAccount::$agent_id}.
 */
class WalletAgentAccess
{
    public static function principalAgentId(Request $request): ?string
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey instanceof ApiKey) {
            $meta = is_array($apiKey->metadata) ? $apiKey->metadata : [];

            $agentId = $meta['agent_id'] ?? null;

            return is_string($agentId) && $agentId !== '' ? $agentId : null;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $token = $user->token();

        if ($token instanceof Token) {
            return (string) $token->client_id;
        }

        return null;
    }

    public static function canAccessWallet(Request $request, WalletAccount $wallet): bool
    {
        if (! app()->bound(CompanyContext::class)) {
            return false;
        }

        /** @var CompanyContext $context */
        $context = app(CompanyContext::class);

        $companyId = $context->companyId();

        if ($companyId === null || (int) $wallet->company_id !== (int) $companyId) {
            return false;
        }

        $env = $context->environment();

        if ($env !== null && (string) $wallet->environment !== (string) $env) {
            return false;
        }

        $principalAgent = self::principalAgentId($request);
        $walletAgent = $wallet->agent_id;

        if ($walletAgent === null || $walletAgent === '') {
            return $principalAgent === null;
        }

        return $principalAgent !== null && (string) $walletAgent === (string) $principalAgent;
    }
}
