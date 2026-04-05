<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        $company = $user->firstCompany();

        return $company !== null && $user->hasCompanyPermission($company, 'company.webhooks.manage');
    }

    public function view(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->ownsEndpoint($user, $webhookEndpoint)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->ownsEndpoint($user, $webhookEndpoint)
            && $this->viewAny($user);
    }

    public function delete(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->ownsEndpoint($user, $webhookEndpoint)
            && $this->viewAny($user);
    }

    private function ownsEndpoint(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        $company = $user->firstCompany();

        return $company !== null && (int) $webhookEndpoint->company_id === (int) $company->getKey();
    }
}
