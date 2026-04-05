<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use App\States\Topup\TopupState;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

class Topup extends Model
{
    use HasFactory, HasPublicId, HasStates;

    public static function publicIdPrefix(): string
    {
        return 'top_';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function ($query): void {
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

            $query->whereHas('walletAccount', function ($wallets) use ($companyId): void {
                $wallets->where('company_id', $companyId);
            });

            $environment = $context->environment();

            if ($environment !== null) {
                $query->where('environment', $environment);
            }
        });
    }

    protected $fillable = [
        'wallet_account_id',
        'bank_link_id',
        'authorization_ledger_entry_id',
        'environment',
        'status',
        'amount_usd',
        'idempotency_key',
        'metadata',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TopupState::class,
            'metadata' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
    }

    public function bankLink(): BelongsTo
    {
        return $this->belongsTo(BankLink::class);
    }

    public function authorizationLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(AuthorizationLedgerEntry::class, 'authorization_ledger_entry_id');
    }
}
