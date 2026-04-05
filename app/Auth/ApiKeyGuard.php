<?php

namespace App\Auth;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class ApiKeyGuard implements Guard
{
    private bool $resolved = false;

    private ?User $user = null;

    private ?ApiKey $apiKey = null;

    public function __construct(private readonly Request $request) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function user(): ?Authenticatable
    {
        if (! $this->resolved) {
            $this->resolveFromBearerToken();
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $token = $credentials['token'] ?? null;

        if (! is_string($token) || $token === '') {
            return false;
        }

        return ApiKey::query()
            ->where('key_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('status', 'active')
            ->exists();
    }

    public function setUser(Authenticatable $user): static
    {
        if ($user instanceof User) {
            $this->user = $user;
            $this->resolved = true;
        }

        return $this;
    }

    public function currentApiKey(): ?ApiKey
    {
        $this->user();

        return $this->apiKey;
    }

    private function resolveFromBearerToken(): void
    {
        $this->resolved = true;

        $bearerToken = $this->request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return;
        }

        $apiKey = ApiKey::query()
            ->where('key_hash', hash('sha256', $bearerToken))
            ->whereNull('revoked_at')
            ->where('status', 'active')
            ->first();

        if (! $apiKey instanceof ApiKey) {
            return;
        }

        /** @var Company|null $company */
        $company = Company::query()->find($apiKey->company_id);

        if (! $company instanceof Company) {
            return;
        }

        /** @var User|null $owner */
        $owner = $company->owner()->first();

        if (! $owner instanceof User) {
            return;
        }

        $this->apiKey = $apiKey;
        $this->user = $owner;

        $this->request->attributes->set('api_key', $apiKey);
    }
}
