<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use App\States\WalletAccount\WalletAccountState;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

class WalletAccount extends Model
{
    use HasFactory, HasPublicId, HasStates;

    public static function publicIdPrefix(): string
    {
        return 'act_';
    }

    protected $fillable = [
        'company_id',
        'user_id',
        'agent_id',
        'environment',
        'status',
        'partner_account_id',
        'balance_cents',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WalletAccountState::class,
            'metadata' => 'array',
            'balance_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            if (! app()->bound(CompanyContext::class)) {
                return;
            }

            /** @var CompanyContext $context */
            $context = app(CompanyContext::class);

            if ($context->bypassesCompanyScope()) {
                return;
            }

            $companyId = $context->companyId();

            if ($companyId === null) {
                return;
            }

            $query->where(function (Builder $q) use ($companyId): void {
                $q->where('wallet_accounts.company_id', $companyId)
                    ->orWhereNull('wallet_accounts.company_id');
            });

            $environment = $context->environment();

            if ($environment !== null) {
                $query->where('wallet_accounts.environment', $environment);
            }
        });
    }

    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company');
    }

    public function scopePersonal(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function scopeForEnvironment(Builder $query, ?string $environment = null): Builder
    {
        $resolvedEnvironment = $environment;

        if ($resolvedEnvironment === null && app()->bound(CompanyContext::class)) {
            /** @var CompanyContext $context */
            $context = app(CompanyContext::class);
            $resolvedEnvironment = $context->environment();
        }

        if ($resolvedEnvironment === null) {
            return $query;
        }

        return $query->where($this->qualifyColumn('environment'), $resolvedEnvironment);
    }

    public function isPersonal(): bool
    {
        return $this->company_id === null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankLinks(): HasMany
    {
        return $this->hasMany(BankLink::class);
    }

    public function kycVerifications(): HasMany
    {
        return $this->hasMany(WalletKycVerification::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function topups(): HasMany
    {
        return $this->hasMany(Topup::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_wallet_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_wallet_account_id');
    }

    public function policy(): HasOne
    {
        return $this->hasOne(Policy::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Reads balance from ledger (source of truth). Prefer the cached
     * balance_cents column for reads; use this to reconcile.
     */
    public function computedBalanceCents(): int
    {
        $entry = $this->ledgerEntries()
            ->orderByDesc('id')
            ->first();

        if (! $entry instanceof LedgerEntry) {
            return 0;
        }

        return (int) $entry->balance_after_cents;
    }

    public function balanceUsd(): float
    {
        return ((int) $this->balance_cents) / 100;
    }
}
