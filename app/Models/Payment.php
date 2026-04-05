<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use App\States\Payment\PaymentState;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\ModelStates\HasStates;

class Payment extends Model
{
    use HasFactory, HasPublicId, HasStates;

    public static function publicIdPrefix(): string
    {
        return 'pay_';
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
        'environment',
        'status',
        'direction',
        'rail',
        'payee_ref',
        'idempotency_key',
        'amount_usd',
        'metadata',
        'held_reason',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentState::class,
            'metadata' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
    }

    public function complianceFlags(): MorphMany
    {
        return $this->morphMany(ComplianceFlag::class, 'flaggable');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }
}
