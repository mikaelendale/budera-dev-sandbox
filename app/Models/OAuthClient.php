<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;

class OAuthClient extends Client
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return (bool) config('budera.oauth.sandbox_auto_approve', false);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
