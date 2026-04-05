<?php

namespace App\Routing;

use App\Http\Responses\ApiErrorResponse;
use App\Models\WalletAccount;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class WalletAccountRouteBinding
{
    public static function register(): void
    {
        Route::bind('walletAccount', function (string $value): WalletAccount {
            return self::resolve($value, request());
        });
    }

    public static function resolve(string $value, Request $request): WalletAccount
    {
        $wallet = WalletAccount::query()
            ->withoutGlobalScope('company')
            ->where('public_id', $value)
            ->first();

        if ($wallet === null) {
            throw (new ModelNotFoundException)->setModel(WalletAccount::class, [$value]);
        }

        if (! app()->bound(CompanyContext::class)) {
            return $wallet;
        }

        /** @var CompanyContext $context */
        $context = app(CompanyContext::class);

        if ($context->bypassesCompanyScope()) {
            return $wallet;
        }

        $companyId = $context->companyId();

        if ($companyId !== null && (int) $wallet->company_id !== (int) $companyId) {
            self::denyForeignWallet($request, $wallet);
        }

        $env = $context->environment();

        if ($env !== null && (string) $wallet->environment !== (string) $env) {
            self::denyEnvironmentMismatch($request, $wallet, (string) $env);
        }

        return $wallet;
    }

    private static function denyForeignWallet(Request $request, WalletAccount $wallet): never
    {
        if ($request->is('api/*')) {
            throw new HttpResponseException(
                ApiErrorResponse::json('wallet_not_in_company', null, [
                    'wallet_public_id' => $wallet->public_id,
                ])
            );
        }

        abort(403);
    }

    private static function denyEnvironmentMismatch(Request $request, WalletAccount $wallet, string $keyEnvironment): never
    {
        if ($request->is('api/*')) {
            throw new HttpResponseException(
                ApiErrorResponse::json('wallet_environment_mismatch', null, [
                    'wallet_public_id' => $wallet->public_id,
                    'wallet_environment' => (string) $wallet->environment,
                    'key_environment' => $keyEnvironment,
                ])
            );
        }

        abort(403);
    }
}
