<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use App\States\BankLink\BankLinkState;
use App\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\HasStates;

class BankLink extends Model
{
    use HasFactory, HasPublicId, HasStates;

    public static function publicIdPrefix(): string
    {
        return 'bl_';
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

            $pivotTable = config('permission.table_names.model_has_roles');
            $teamKey = config('permission.column_names.team_foreign_key');
            $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

            $query->whereHas('user', function ($users) use ($companyId, $pivotTable, $teamKey, $modelMorphKey): void {
                $users->whereExists(function ($membershipQuery) use ($companyId, $pivotTable, $teamKey, $modelMorphKey): void {
                    $membershipQuery->select(DB::raw(1))
                        ->from($pivotTable)
                        ->whereColumn($pivotTable.'.'.$modelMorphKey, 'users.id')
                        ->where($pivotTable.'.model_type', (new User)->getMorphClass())
                        ->where($pivotTable.'.'.$teamKey, $companyId);
                });
            });

            $environment = $context->environment();

            if ($environment !== null) {
                $query->where('environment', $environment);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'wallet_account_id',
        'company_id',
        'session_token_hash',
        'session_expires_at',
        'environment',
        'status',
        'bank_slug',
        'account_last4',
        'routing_hash',
        'encrypted_routing',
        'encrypted_account',
        'failed_verification_attempts',
        'verified_at',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankLinkState::class,
            'encrypted_routing' => 'encrypted',
            'encrypted_account' => 'encrypted',
            'failed_verification_attempts' => 'integer',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
            'session_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function topups(): HasMany
    {
        return $this->hasMany(Topup::class);
    }
}
