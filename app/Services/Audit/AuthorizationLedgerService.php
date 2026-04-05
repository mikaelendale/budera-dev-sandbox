<?php

namespace App\Services\Audit;

use App\Models\AuthorizationLedgerEntry;
use App\Models\BankLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AuthorizationLedgerService
{
    public function __construct(
        private readonly CryptoSigner $cryptoSigner,
    ) {}

    /**
     * Record a user-facing authorization (e.g. ACH standing debit consent) as a signed, append-only ledger row.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordAuthorization(
        string $type,
        User $user,
        ?int $accountId,
        string $textPresented,
        ?string $ip,
        ?string $userAgent,
        ?string $environment,
        array $metadata = [],
    ): AuthorizationLedgerEntry {
        $correlationId = CorrelationId::fromRequestOrGenerate();
        $recordedAt = now()->toIso8601String();

        $payload = [
            'record_kind' => 'ach_standing_consent',
            'authorization_type' => $type,
            'text_presented' => $textPresented,
            'text_sha256' => hash('sha256', $textPresented),
            'recorded_at' => $recordedAt,
            'actor' => [
                'type' => 'user',
                'id' => (string) $user->getKey(),
            ],
            'account_id' => $accountId,
            'correlation_id' => $correlationId,
            'environment' => $environment,
            'metadata' => $metadata,
        ];

        $signed = $this->cryptoSigner->sign($payload);

        return DB::transaction(function () use (
            $signed,
            $correlationId,
            $environment,
            $ip,
            $userAgent,
            $accountId,
            $user,
            $type,
            $metadata,
        ): AuthorizationLedgerEntry {
            return AuthorizationLedgerEntry::query()->create([
                'stream' => 'developer',
                'actor_type' => 'user',
                'actor_id' => (string) $user->getKey(),
                'authorization_text' => (string) $signed['authorization_text'],
                'authorization_hash' => (string) $signed['authorization_hash'],
                'authorization_signature' => (string) $signed['authorization_signature'],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'account_id' => $accountId,
                'correlation_id' => $correlationId,
                'environment' => $environment,
                'metadata' => array_merge($metadata, [
                    'record_kind' => 'ach_standing_consent',
                    'authorization_type' => $type,
                ]),
            ]);
        });
    }

    public function latestAchConsentForBankLink(BankLink $bankLink): ?AuthorizationLedgerEntry
    {
        return AuthorizationLedgerEntry::query()
            ->where('metadata->record_kind', 'ach_standing_consent')
            ->where('metadata->bank_link_id', (string) $bankLink->getKey())
            ->orderByDesc('id')
            ->first();
    }

    public function resolveLedgerIdForAchTopup(BankLink $bankLink, ?int $explicitId): int
    {
        if ($explicitId !== null) {
            $entry = AuthorizationLedgerEntry::query()->whereKey($explicitId)->first();
            if ($entry === null) {
                throw new InvalidArgumentException('authorization_ledger_entry_not_found');
            }
            $this->assertConsentMatchesBankLink($entry, $bankLink);

            return (int) $entry->getKey();
        }

        $latest = $this->latestAchConsentForBankLink($bankLink);
        if ($latest === null) {
            throw new InvalidArgumentException('ach_authorization_ledger_required');
        }

        return (int) $latest->getKey();
    }

    public function assertConsentMatchesBankLink(AuthorizationLedgerEntry $entry, BankLink $bankLink): void
    {
        $meta = is_array($entry->metadata) ? $entry->metadata : [];
        if (($meta['record_kind'] ?? '') !== 'ach_standing_consent') {
            throw new InvalidArgumentException('authorization_ledger_not_ach_consent');
        }
        if (($meta['bank_link_id'] ?? '') !== (string) $bankLink->getKey()) {
            throw new InvalidArgumentException('authorization_ledger_bank_link_mismatch');
        }
    }
}
