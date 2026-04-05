<?php

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\KybReview;
use App\Models\User;
use App\Models\WebhookOutbox;
use App\Notifications\Transactional\KybApprovedNotification;
use App\Notifications\Transactional\LiveAccessApprovedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * @return array{questionnaire: array<string, mixed>}
 */
function validKybSubmissionPayload(User $owner): array
{
    return [
        'questionnaire' => [
            'entity' => [
                'legal_name' => 'Test KYB Owner',
                'entity_type' => 'individual',
                'country' => 'US',
                'date_of_birth' => '1991-06-15',
                'registration_number' => '',
                'registered_address' => "1 Test Lane\nTest City, TS 00000",
            ],
            'ownership' => [
                'primary_operator_name' => 'Test Owner',
                'primary_operator_role' => 'Founder',
                'beneficial_owners' => '',
                'control_person_ai' => 'Test Owner approves agent policies.',
            ],
            'contact' => [
                'email' => $owner->email,
                'phone' => '+15555559000',
                'website_url' => 'https://kyb-test.example.com',
                'product_description' => 'AI agents with financial tooling.',
            ],
            'platform' => [
                'usage_internal' => true,
                'usage_platform' => false,
            ],
            'end_user_exposure' => [
                'launch_estimate' => '',
                'end_users_have_wallets' => '',
                'agents_act_for_users' => '',
                'funds_owner' => '',
                'user_agent_interaction' => '',
            ],
            'compliance' => [
                'kyc_on_end_users' => 'no',
                'kyc_provider' => '',
                'kyc_data_collected' => '',
                'kyc_no_explanation' => 'Sandbox-only users; production will add KYC.',
                'sanctions_screening' => 'no',
            ],
            'agent' => [
                'actions' => ['view_balances', 'initiate_payments'],
                'autonomy_level' => 'partial',
            ],
            'financial' => [
                'max_transaction_amount' => '5000 USD',
                'expected_monthly_volume' => '25000 USD',
                'expected_tx_per_month' => '120',
                'supported_regions' => 'US, CA, GB',
            ],
            'funds_flow' => [
                'source' => 'End-user-linked bank accounts',
                'destination' => 'Merchant payments and subscriptions',
                'hold_funds_others' => 'no',
                'description' => 'Funds move from user funding sources to payees via Budera wallets.',
            ],
            'controls' => [
                'spending_limits_per_agent' => 'yes',
                'users_override_cancel' => 'yes',
                'log_agent_actions' => 'yes',
                'realtime_monitoring' => 'yes',
                'kill_switch' => 'yes',
            ],
            'risk' => [
                'worst_case_failure' => 'Erroneous high-value payment',
                'incorrect_payments' => 'Manual review and reversal workflow',
                'compromised_accounts' => 'Revoke tokens and freeze wallet',
                'prompt_injection' => 'Scoped tools and human approval for high-risk actions',
            ],
            'integration' => [
                'backend' => true,
                'client_side' => false,
                'api_use_case' => 'Server-side agent calls Budera API with company API key.',
                'webhook_endpoint' => '',
                'hosting_region' => 'us-east-1',
            ],
            'declarations' => [
                'no_anonymous_financial' => true,
                'aml_sanctions' => true,
                'end_user_activity_responsibility' => true,
                'terms_of_service' => true,
            ],
        ],
    ];
}

test('company owner can open kyb application form', function () {
    $owner = User::factory()->withCompany('Acme KYB Form')->create();

    $this->actingAs($owner)
        ->get(route('company.kyb.form'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('company/kyb-submit'));
});

test('company owner submits kyb admin approves enabling live api keys mail and webhook outbox', function () {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme KYB')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $payload = validKybSubmissionPayload($owner);
    $payload['document_government_id'] = UploadedFile::fake()->create('id.pdf', 200, 'application/pdf');

    $this->actingAs($owner)
        ->from(route('company.settings'))
        ->post(route('company.kyb.submit'), $payload)
        ->assertRedirect(route('company.settings'))
        ->assertSessionHas('status');

    $review = KybReview::query()->where('company_id', $company->id)->firstOrFail();
    expect($review->status->getValue())->toBe('pending')
        ->and($company->fresh()->kyb_status)->toBe('pending')
        ->and($review->questionnaire)->toBeArray()
        ->and($review->questionnaire['entity']['legal_name'])->toBe('Test KYB Owner')
        ->and($review->documents)->toBeArray()
        ->and($review->documents)->toHaveKey('government_id');

    $admin = User::factory()->buderaAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.kyb-reviews.start-review', $review))
        ->assertRedirect(route('admin.kyb-reviews.show', $review));

    expect($review->fresh()->status->getValue())->toBe('under_review');

    $this->actingAs($admin)
        ->post(route('admin.kyb-reviews.approve', $review))
        ->assertRedirect(route('admin.kyb-reviews.index'));

    $company->refresh();
    expect($company->live_enabled_at)->toBeNull()
        ->and($company->kyb_status)->toBe('approved');

    Notification::assertSentTo($owner, KybApprovedNotification::class, function (KybApprovedNotification $n) use ($company): bool {
        return (int) $n->company->getKey() === (int) $company->getKey();
    });

    expect(WebhookOutbox::query()->where('event', 'kyb.approved')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.live-access.approve', $company))
        ->assertRedirect(route('admin.live-access.index'))
        ->assertSessionHas('status');

    $company->refresh();
    expect($company->live_enabled_at)->not->toBeNull();

    Notification::assertSentTo($owner, LiveAccessApprovedNotification::class, function (LiveAccessApprovedNotification $n) use ($company): bool {
        return (int) $n->company->getKey() === (int) $company->getKey();
    });

    expect(WebhookOutbox::query()->where('event', 'live.enabled')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->from(route('company.api-keys.index'))
        ->post(route('company.api-keys.store'), [
            'environment' => 'live',
            'abilities' => ['wallet:read'],
        ])
        ->assertRedirect(route('company.api-keys.index'))
        ->assertSessionHas('one_time_plain_text_key');

    $apiKey = ApiKey::query()->where('company_id', $company->id)->latest()->first();
    expect($apiKey)->not->toBeNull();
    expect($apiKey->environment)->toBe('live');
});
