<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletOauthGrant extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'oauth_access_token_id',
        'user_id',
        'oauth_client_id',
        'company_id',
        'wallet_account_id',
        'scopes',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
