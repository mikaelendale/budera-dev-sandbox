<?php

namespace App\Services\BankLink;

use App\Contracts\BankLink\BankLinkService;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Transactional\MicrodepositInstructionsNotification;
use App\Services\Audit\AuthorizationLedgerService;
use App\Services\Audit\TransitionRecorder;
use App\Services\Mail\TransactionalMail;
use App\States\BankLink\BankLinkFailed;
use App\States\BankLink\BankLinkInitiated;
use App\States\BankLink\BankLinkMicrodepositSent;
use App\States\BankLink\BankLinkRevoked;
use App\States\BankLink\BankLinkVerified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MockBankLinkService implements BankLinkService
{
    public function __construct(
        private readonly TransitionRecorder $transitionRecorder,
        private readonly AuthorizationLedgerService $authorizationLedgerService,
    ) {}

    public function createHostedSession(User $endUser, Company $company, string $environment): array
    {
        $plain = Str::random(64);

        $link = DB::transaction(function () use ($endUser, $company, $environment, $plain): BankLink {
            $link = BankLink::query()->create([
                'user_id' => $endUser->getKey(),
                'company_id' => $company->getKey(),
                'environment' => $environment,
                'status' => 'initiated',
                'session_token_hash' => hash('sha256', $plain),
                'session_expires_at' => now()->addHours((int) config('budera.bank_link.session_ttl_hours', 72)),
                'bank_slug' => null,
                'account_last4' => null,
                'routing_hash' => null,
                'encrypted_routing' => null,
                'encrypted_account' => null,
                'failed_verification_attempts' => 0,
                'verified_at' => null,
                'revoked_at' => null,
                'metadata' => [
                    'hosted_session' => true,
                ],
            ]);

            $fresh = $link->fresh();
            if ($fresh === null) {
                throw new \RuntimeException('bank_link_hosted_session_refresh_failed');
            }

            return $fresh;
        });

        return [
            'plain_session_token' => $plain,
            'bankLink' => $link,
        ];
    }

    /**
     * @param  array{routing_number: string, account_number: string, bank_slug?: string|null}  $credentials
     */
    public function submitCredentials(BankLink $link, array $credentials): BankLink
    {
        $routing = preg_replace('/\D/', '', (string) $credentials['routing_number']) ?? '';
        $account = preg_replace('/\D/', '', (string) $credentials['account_number']) ?? '';

        if (strlen($routing) !== 9) {
            throw new InvalidArgumentException('routing_number_invalid');
        }

        if (strlen($account) < 4 || strlen($account) > 17) {
            throw new InvalidArgumentException('account_number_invalid');
        }

        $expectedCents = config('budera.bank_link.sandbox_microdeposit_cents', [12, 34]);
        if (! is_array($expectedCents) || count($expectedCents) !== 2) {
            throw new \RuntimeException('bank_link_sandbox_amounts_misconfigured');
        }
        $expectedCents = array_map(intval(...), $expectedCents);

        $bankSlug = isset($credentials['bank_slug']) && is_string($credentials['bank_slug']) && $credentials['bank_slug'] !== ''
            ? $credentials['bank_slug']
            : null;

        return DB::transaction(function () use ($link, $routing, $account, $bankSlug, $expectedCents): BankLink {
            $locked = BankLink::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status instanceof BankLinkInitiated) {
                throw new InvalidArgumentException('bank_link_credentials_already_submitted');
            }

            $user = User::query()->find($locked->user_id);
            if (! $user instanceof User) {
                throw new \RuntimeException('bank_link_user_missing');
            }

            $meta = is_array($locked->metadata) ? $locked->metadata : [];
            $locked->bank_slug = $bankSlug;
            $locked->account_last4 = substr($account, -4);
            $locked->routing_hash = hash('sha256', $routing);
            $locked->encrypted_routing = $routing;
            $locked->encrypted_account = $account;
            $locked->metadata = array_merge($meta, [
                'microdeposit_expected_cents' => $expectedCents,
                'sandbox_microdeposit_documentation' => 'Sandbox verification amounts are $0.12 and $0.34 (12 and 34 cents), in either order.',
            ]);

            $from = $locked->status->getValue();
            $locked->status->transitionTo(BankLinkMicrodepositSent::class);
            $locked->save();

            $fresh = $locked->fresh();
            if ($fresh === null) {
                throw new \RuntimeException('bank_link_submit_credentials_refresh_failed');
            }

            $this->transitionRecorder->record(
                $fresh,
                $from,
                'microdeposit_sent',
                [
                    'stream' => 'developer',
                    'actor_type' => 'user',
                    'actor_id' => (string) $user->getKey(),
                    'action' => 'bank_link.session.started',
                    'resource_type' => 'bank_links',
                    'resource_id' => (string) $fresh->getKey(),
                    'environment' => $fresh->environment,
                    'metadata' => [
                        'bank_link_public_id' => $fresh->public_id,
                        'user_id' => (string) $fresh->user_id,
                    ],
                ],
            );

            $metaAfter = is_array($fresh->metadata) ? $fresh->metadata : [];
            $doc = isset($metaAfter['sandbox_microdeposit_documentation']) && is_string($metaAfter['sandbox_microdeposit_documentation'])
                ? $metaAfter['sandbox_microdeposit_documentation']
                : null;
            TransactionalMail::notifyUser($user, new MicrodepositInstructionsNotification($fresh, $expectedCents, $doc));

            return $fresh;
        });
    }

    /**
     * @param  array{routing_number: string, account_number: string, bank_slug?: string|null}  $credentials
     */
    public function startSession(User $user, string $environment, array $credentials, ?int $companyId = null): BankLink
    {
        return DB::transaction(function () use ($user, $environment, $credentials, $companyId): BankLink {
            $link = BankLink::query()->create([
                'user_id' => $user->getKey(),
                'company_id' => $companyId,
                'environment' => $environment,
                'status' => 'initiated',
                'bank_slug' => null,
                'account_last4' => null,
                'routing_hash' => null,
                'encrypted_routing' => null,
                'encrypted_account' => null,
                'failed_verification_attempts' => 0,
                'verified_at' => null,
                'revoked_at' => null,
                'metadata' => [],
            ]);

            $fresh = $link->fresh();
            if ($fresh === null) {
                throw new \RuntimeException('bank_link_start_create_refresh_failed');
            }

            return $this->submitCredentials($fresh, $credentials);
        });
    }

    public function verifyMicrodeposits(BankLink $link, User $actor, int $amountFirstCents, int $amountSecondCents): BankLink
    {
        $verified = null;
        $verificationMismatch = false;

        DB::transaction(function () use ($link, $actor, $amountFirstCents, $amountSecondCents, &$verified, &$verificationMismatch): void {
            $locked = BankLink::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status instanceof BankLinkMicrodepositSent) {
                throw new InvalidArgumentException('bank_link_not_awaiting_verification');
            }

            $meta = is_array($locked->metadata) ? $locked->metadata : [];
            $expected = $meta['microdeposit_expected_cents'] ?? null;

            if (! is_array($expected) || count($expected) !== 2) {
                throw new \RuntimeException('bank_link_missing_expected_amounts');
            }

            $expectedSorted = array_values(array_map(intval(...), $expected));
            sort($expectedSorted);

            $provided = [$amountFirstCents, $amountSecondCents];
            sort($provided);

            if ($provided !== $expectedSorted) {
                $locked->failed_verification_attempts = (int) $locked->failed_verification_attempts + 1;
                $locked->save();

                if ($locked->failed_verification_attempts >= 3) {
                    $fromFailed = $locked->status->getValue();
                    $locked->status->transitionTo(BankLinkFailed::class);
                    $locked->save();
                    $failedFresh = $locked->fresh();
                    if ($failedFresh !== null) {
                        $this->transitionRecorder->record(
                            $failedFresh,
                            $fromFailed,
                            'failed',
                            [
                                'stream' => 'developer',
                                'actor_type' => 'user',
                                'actor_id' => (string) $actor->getKey(),
                                'action' => 'bank_link.verification_failed',
                                'resource_type' => 'bank_links',
                                'resource_id' => (string) $failedFresh->getKey(),
                                'environment' => $failedFresh->environment,
                                'metadata' => [
                                    'bank_link_public_id' => $failedFresh->public_id,
                                    'failed_verification_attempts' => (string) $failedFresh->failed_verification_attempts,
                                ],
                            ],
                        );
                    }
                }

                $verificationMismatch = true;

                return;
            }

            $fromOk = $locked->status->getValue();
            $locked->status->transitionTo(BankLinkVerified::class);
            $locked->verified_at = now();
            $locked->save();

            $fresh = $locked->fresh();
            if ($fresh === null) {
                throw new \RuntimeException('bank_link_verify_refresh_failed');
            }

            $this->transitionRecorder->record(
                $fresh,
                $fromOk,
                'verified',
                [
                    'stream' => 'developer',
                    'actor_type' => 'user',
                    'actor_id' => (string) $actor->getKey(),
                    'action' => 'bank_link.ach_standing_authorization.recorded',
                    'resource_type' => 'bank_links',
                    'resource_id' => (string) $fresh->getKey(),
                    'environment' => $fresh->environment,
                    'metadata' => [
                        'bank_link_public_id' => $fresh->public_id,
                        'authorization_purpose' => 'ach_debit_standing',
                        'user_id' => (string) $fresh->user_id,
                    ],
                ],
            );

            $this->authorizationLedgerService->recordAuthorization(
                'ach_debit_standing',
                $actor,
                null,
                (string) config('budera.ach.standing_authorization_text'),
                request()?->ip(),
                request()?->userAgent(),
                $fresh->environment,
                [
                    'bank_link_id' => (string) $fresh->getKey(),
                    'bank_link_public_id' => (string) $fresh->public_id,
                ],
            );

            $verified = $fresh;
        });

        if ($verificationMismatch) {
            throw new InvalidArgumentException('microdeposit_verification_failed');
        }

        if ($verified === null) {
            throw new \RuntimeException('bank_link_verify_no_result');
        }

        return $verified;
    }

    public function revoke(BankLink $link, User $actor): BankLink
    {
        return DB::transaction(function () use ($link, $actor): BankLink {
            $locked = BankLink::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status instanceof BankLinkRevoked) {
                return $locked;
            }

            if (! $locked->status instanceof BankLinkMicrodepositSent && ! $locked->status instanceof BankLinkVerified) {
                throw new InvalidArgumentException('bank_link_cannot_revoke');
            }

            $from = $locked->status->getValue();
            $locked->status->transitionTo(BankLinkRevoked::class);
            $locked->revoked_at = now();
            $locked->save();

            $fresh = $locked->fresh();
            if ($fresh !== null) {
                $this->transitionRecorder->record(
                    $fresh,
                    $from,
                    'revoked',
                    [
                        'stream' => 'developer',
                        'actor_type' => 'user',
                        'actor_id' => (string) $actor->getKey(),
                        'action' => 'bank_link.revoked',
                        'resource_type' => 'bank_links',
                        'resource_id' => (string) $fresh->getKey(),
                        'environment' => $fresh->environment,
                        'metadata' => [
                            'bank_link_public_id' => $fresh->public_id,
                            'user_id' => (string) $fresh->user_id,
                        ],
                    ],
                );
            }

            return $locked->fresh();
        });
    }
}
