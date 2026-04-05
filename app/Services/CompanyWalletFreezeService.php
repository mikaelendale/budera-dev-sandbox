<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\WalletAccount;
use App\Notifications\Transactional\AccountFrozenNotification;
use App\Services\Audit\CorrelationId;
use App\Services\Audit\TransitionRecorder;
use App\Services\Mail\TransactionalMail;
use App\States\WalletAccount\WalletAccountActive;
use App\States\WalletAccount\WalletAccountFrozen;
use App\States\WalletAccount\WalletAccountPaused;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyWalletFreezeService
{
    public function __construct(
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    /**
     * @return array{frozen: int, skipped: int}
     */
    public function freezeAllCompanyWallets(Company $company, User $admin, ?Request $request = null): array
    {
        $result = ['frozen' => 0, 'skipped' => 0];

        DB::transaction(function () use ($company, $admin, $request, &$result): void {
            $wallets = WalletAccount::query()
                ->where('company_id', $company->getKey())
                ->lockForUpdate()
                ->get();

            foreach ($wallets as $wallet) {
                if ($wallet->status instanceof WalletAccountFrozen) {
                    $result['skipped']++;

                    continue;
                }

                if (! $wallet->status instanceof WalletAccountActive && ! $wallet->status instanceof WalletAccountPaused) {
                    $result['skipped']++;

                    continue;
                }

                $from = $wallet->status->getValue();
                $wallet->status->transitionTo(WalletAccountFrozen::class);
                $wallet->save();

                $fresh = $wallet->fresh();
                if ($fresh === null) {
                    continue;
                }

                $this->transitionRecorder->record(
                    $fresh,
                    $from,
                    'frozen',
                    [
                        'stream' => 'internal_admin',
                        'actor_type' => 'user',
                        'actor_id' => (string) $admin->getKey(),
                        'action' => 'wallet.account.frozen',
                        'resource_type' => 'wallet_accounts',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'metadata' => [
                            'company_id' => (string) $company->getKey(),
                            'admin_user_id' => (string) $admin->getKey(),
                            'reason' => 'company_freeze',
                        ],
                        'correlation_id' => $request !== null ? CorrelationId::current($request) : null,
                        'ip_address' => $request?->ip(),
                        'user_agent' => $request?->userAgent(),
                        'account_id' => (int) $fresh->getKey(),
                        'webhook_event' => 'account.frozen',
                        'webhook_payload' => [
                            'event' => 'account.frozen',
                            'data' => [
                                'wallet_account_id' => (string) $fresh->public_id,
                                'company_id' => (string) $company->getKey(),
                            ],
                        ],
                    ],
                );

                $user = $fresh->user()->first();
                TransactionalMail::notifyUser($user, new AccountFrozenNotification($fresh));

                $result['frozen']++;
            }
        });

        return $result;
    }

    /**
     * @return array{unfrozen: int, skipped: int}
     */
    public function unfreezeAllCompanyWallets(Company $company, User $admin, ?Request $request = null): array
    {
        $result = ['unfrozen' => 0, 'skipped' => 0];

        DB::transaction(function () use ($company, $admin, $request, &$result): void {
            $wallets = WalletAccount::query()
                ->where('company_id', $company->getKey())
                ->lockForUpdate()
                ->get();

            foreach ($wallets as $wallet) {
                if (! $wallet->status instanceof WalletAccountFrozen) {
                    $result['skipped']++;

                    continue;
                }

                $from = $wallet->status->getValue();
                $wallet->status->transitionTo(WalletAccountActive::class);
                $wallet->save();

                $fresh = $wallet->fresh();
                if ($fresh === null) {
                    continue;
                }

                $this->transitionRecorder->record(
                    $fresh,
                    $from,
                    'active',
                    [
                        'stream' => 'internal_admin',
                        'actor_type' => 'user',
                        'actor_id' => (string) $admin->getKey(),
                        'action' => 'wallet.account.unfrozen',
                        'resource_type' => 'wallet_accounts',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'metadata' => [
                            'company_id' => (string) $company->getKey(),
                            'admin_user_id' => (string) $admin->getKey(),
                            'reason' => 'company_unfreeze',
                        ],
                        'correlation_id' => $request !== null ? CorrelationId::current($request) : null,
                        'ip_address' => $request?->ip(),
                        'user_agent' => $request?->userAgent(),
                        'account_id' => (int) $fresh->getKey(),
                        'webhook_event' => 'account.unfrozen',
                        'webhook_payload' => [
                            'event' => 'account.unfrozen',
                            'data' => [
                                'wallet_account_id' => (string) $fresh->public_id,
                                'company_id' => (string) $company->getKey(),
                            ],
                        ],
                    ],
                );

                $result['unfrozen']++;
            }
        });

        return $result;
    }
}
