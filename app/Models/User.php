<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'user_type'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_budera_admin' => 'boolean',
        ];
    }

    public function canAccessDashboard(): bool
    {
        if ($this->is_budera_admin) {
            return true;
        }

        return $this->hasCompanyMembership();
    }

    public function isEndUser(): bool
    {
        return $this->user_type === 'end_user';
    }

    public function hasCompanyMembership(): bool
    {
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $pivotTable = config('permission.table_names.model_has_roles');

        return DB::table($pivotTable)
            ->where('model_id', $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->whereNotNull($teamsKey)
            ->exists();
    }

    public function firstCompany(): ?Company
    {
        if (! $this->hasCompanyMembership()) {
            return null;
        }

        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $pivotTable = config('permission.table_names.model_has_roles');

        $companyId = DB::table($pivotTable)
            ->where('model_id', $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->whereNotNull($teamsKey)
            ->orderBy($teamsKey)
            ->value($teamsKey);

        if ($companyId === null) {
            return null;
        }

        return Company::query()->find((int) $companyId);
    }

    public function hasCompanyRole(Company $company, string $roleName): bool
    {
        setPermissionsTeamId($company->getKey());

        try {
            return $this->hasRole($roleName);
        } finally {
            setPermissionsTeamId(null);
        }
    }

    /**
     * Only company owners can send or revoke invitations for this release.
     */
    public function canManageCompanyInvites(Company $company): bool
    {
        return $this->hasCompanyPermission($company, 'company.members.manage');
    }

    public function hasCompanyPermission(Company $company, string $permission): bool
    {
        setPermissionsTeamId($company->getKey());

        try {
            return $this->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        } finally {
            setPermissionsTeamId(null);
        }
    }

    public function isMemberOfCompany(Company $company): bool
    {
        return $this->hasCompanyRole($company, 'company_owner')
            || $this->hasCompanyRole($company, 'company_developer');
    }

    /**
     * True if the user has any team-scoped role for the company (including end_user).
     */
    public function isAssociatedWithCompany(Company $company): bool
    {
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $pivotTable = config('permission.table_names.model_has_roles');

        return DB::table($pivotTable)
            ->where('model_id', $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->where($teamsKey, $company->getKey())
            ->exists();
    }

    /**
     * Whether the end user's personal wallet has passed KYC verification.
     */
    public function isKycVerified(): bool
    {
        if (! $this->isEndUser()) {
            return false;
        }

        $wallet = $this->personalWallet()->first();

        if ($wallet === null) {
            return false;
        }

        return $wallet->kycVerifications()
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * The user's personal wallet (company_id is null). End users get
     * exactly one of these, auto-provisioned at registration.
     */
    public function personalWallet(): HasOne
    {
        return $this->hasOne(WalletAccount::class)->whereNull('company_id');
    }

    public function walletAccounts(): HasMany
    {
        return $this->hasMany(WalletAccount::class);
    }

    public function bankLinks(): HasMany
    {
        return $this->hasMany(BankLink::class);
    }
}
