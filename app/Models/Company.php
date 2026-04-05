<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_url',
        'email',
        'owner_id',
        'kyb_status',
        'live_enabled_at',
        'sandbox_limit_overrides',
        'api_rate_limit_tier',
    ];

    protected function casts(): array
    {
        return [
            'live_enabled_at' => 'datetime',
            'sandbox_limit_overrides' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function walletAccounts(): HasMany
    {
        return $this->hasMany(WalletAccount::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function kybReviews(): HasMany
    {
        return $this->hasMany(KybReview::class);
    }

    public function walletOauthGrants(): HasMany
    {
        return $this->hasMany(WalletOauthGrant::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,
            WalletAccount::class,
            'company_id',
            'wallet_account_id',
            'id',
            'id',
        );
    }

    public function topups(): HasManyThrough
    {
        return $this->hasManyThrough(
            Topup::class,
            WalletAccount::class,
            'company_id',
            'wallet_account_id',
            'id',
            'id',
        );
    }

    public function outgoingTransfers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transfer::class,
            WalletAccount::class,
            'company_id',
            'from_wallet_account_id',
            'id',
            'id',
        );
    }

    public function incomingTransfers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transfer::class,
            WalletAccount::class,
            'company_id',
            'to_wallet_account_id',
            'id',
            'id',
        );
    }

    public function bankLinks(): Builder|QueryBuilder
    {
        $pivotTable = config('permission.table_names.model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key');
        $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

        return BankLink::query()
            ->whereIn('user_id', function ($query) use ($pivotTable, $teamKey, $modelMorphKey): void {
                $query->select($modelMorphKey)
                    ->from($pivotTable)
                    ->where('model_type', (new User)->getMorphClass())
                    ->where($teamKey, $this->getKey());
            });
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->getKey();
    }

    /**
     * @return Collection<int, object{id: int, name: string, email: string, role: string}>
     */
    public function membersWithRoles(): Collection
    {
        $pivot = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');
        $teamKey = config('permission.column_names.team_foreign_key');

        return DB::table($pivot)
            ->where($pivot.'.'.$teamKey, $this->id)
            ->where($pivot.'.model_type', (new User)->getMorphClass())
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivot}.role_id")
            ->join('users', 'users.id', '=', "{$pivot}.model_id")
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                "{$rolesTable}.name as role",
            ])
            ->get();
    }
}
