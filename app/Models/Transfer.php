<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use App\States\Transfer\TransferState;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

class Transfer extends Model
{
    use HasFactory, HasPublicId, HasStates;

    public static function publicIdPrefix(): string
    {
        return 'txfr_';
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

            $query->where(function ($q) use ($companyId): void {
                $q->whereHas('fromWalletAccount', function ($wallets) use ($companyId): void {
                    $wallets->where('company_id', $companyId);
                })->orWhereHas('toWalletAccount', function ($wallets) use ($companyId): void {
                    $wallets->where('company_id', $companyId);
                });
            });

            $environment = $context->environment();

            if ($environment !== null) {
                $query->where('environment', $environment);
            }
        });
    }

    protected $fillable = [
        'from_wallet_account_id',
        'to_wallet_account_id',
        'environment',
        'status',
        'amount_usd',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferState::class,
            'metadata' => 'array',
        ];
    }

    public function fromWalletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'from_wallet_account_id');
    }

    public function toWalletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'to_wallet_account_id');
    }
}
