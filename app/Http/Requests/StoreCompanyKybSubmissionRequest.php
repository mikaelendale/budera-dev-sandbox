<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyKybSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $company = $user->firstCompany();

        return $company !== null
            && (int) $company->owner_id === (int) $user->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'questionnaire' => ['required', 'array'],

            'questionnaire.entity' => ['required', 'array'],
            'questionnaire.entity.legal_name' => ['required', 'string', 'max:255'],
            'questionnaire.entity.entity_type' => ['required', 'string', Rule::in(['individual', 'sole_proprietor', 'company'])],
            'questionnaire.entity.country' => ['required', 'string', 'max:120'],
            'questionnaire.entity.date_of_birth' => ['nullable', 'string', 'max:40'],
            'questionnaire.entity.registration_number' => ['nullable', 'string', 'max:120'],
            'questionnaire.entity.registered_address' => ['required', 'string', 'max:2000'],

            'questionnaire.ownership' => ['required', 'array'],
            'questionnaire.ownership.primary_operator_name' => ['required', 'string', 'max:255'],
            'questionnaire.ownership.primary_operator_role' => ['required', 'string', 'max:120'],
            'questionnaire.ownership.beneficial_owners' => ['nullable', 'string', 'max:2000'],
            'questionnaire.ownership.control_person_ai' => ['required', 'string', 'max:2000'],

            'questionnaire.contact' => ['required', 'array'],
            'questionnaire.contact.email' => ['required', 'email', 'max:255'],
            'questionnaire.contact.phone' => ['required', 'string', 'max:80'],
            'questionnaire.contact.website_url' => ['required', 'url', 'max:500'],
            'questionnaire.contact.product_description' => ['required', 'string', 'max:8000'],

            'questionnaire.platform' => ['required', 'array'],
            'questionnaire.platform.usage_internal' => ['required', 'boolean'],
            'questionnaire.platform.usage_platform' => ['required', 'boolean'],

            'questionnaire.end_user_exposure' => ['nullable', 'array'],
            'questionnaire.end_user_exposure.launch_estimate' => ['nullable', 'string', 'max:500'],
            'questionnaire.end_user_exposure.end_users_have_wallets' => ['nullable', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.end_user_exposure.agents_act_for_users' => ['nullable', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.end_user_exposure.funds_owner' => ['nullable', 'string', Rule::in(['end_users', 'business'])],
            'questionnaire.end_user_exposure.user_agent_interaction' => ['nullable', 'string', 'max:4000'],

            'questionnaire.compliance' => ['required', 'array'],
            'questionnaire.compliance.kyc_on_end_users' => ['required', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.compliance.kyc_provider' => ['nullable', 'string', 'max:500'],
            'questionnaire.compliance.kyc_data_collected' => ['nullable', 'string', 'max:2000'],
            'questionnaire.compliance.kyc_no_explanation' => ['nullable', 'string', 'max:2000'],
            'questionnaire.compliance.sanctions_screening' => ['required', 'string', Rule::in(['yes', 'no'])],

            'questionnaire.agent' => ['required', 'array'],
            'questionnaire.agent.actions' => ['required', 'array', 'min:1'],
            'questionnaire.agent.actions.*' => ['string', Rule::in(['view_balances', 'initiate_payments', 'receive_funds', 'manage_budgets'])],
            'questionnaire.agent.autonomy_level' => ['required', 'string', Rule::in(['manual', 'partial', 'full_within_limits'])],

            'questionnaire.financial' => ['required', 'array'],
            'questionnaire.financial.max_transaction_amount' => ['required', 'string', 'max:120'],
            'questionnaire.financial.expected_monthly_volume' => ['required', 'string', 'max:120'],
            'questionnaire.financial.expected_tx_per_month' => ['required', 'string', 'max:120'],
            'questionnaire.financial.supported_regions' => ['required', 'string', 'max:2000'],

            'questionnaire.funds_flow' => ['required', 'array'],
            'questionnaire.funds_flow.source' => ['required', 'string', 'max:2000'],
            'questionnaire.funds_flow.destination' => ['required', 'string', 'max:2000'],
            'questionnaire.funds_flow.hold_funds_others' => ['required', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.funds_flow.description' => ['required', 'string', 'max:4000'],

            'questionnaire.controls' => ['required', 'array'],
            'questionnaire.controls.spending_limits_per_agent' => ['required', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.controls.users_override_cancel' => ['required', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.controls.log_agent_actions' => ['required', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.controls.realtime_monitoring' => ['required', 'string', Rule::in(['yes', 'no'])],
            'questionnaire.controls.kill_switch' => ['required', 'string', Rule::in(['yes', 'no'])],

            'questionnaire.risk' => ['required', 'array'],
            'questionnaire.risk.worst_case_failure' => ['required', 'string', 'max:4000'],
            'questionnaire.risk.incorrect_payments' => ['required', 'string', 'max:4000'],
            'questionnaire.risk.compromised_accounts' => ['required', 'string', 'max:4000'],
            'questionnaire.risk.prompt_injection' => ['required', 'string', 'max:4000'],

            'questionnaire.integration' => ['required', 'array'],
            'questionnaire.integration.backend' => ['required', 'boolean'],
            'questionnaire.integration.client_side' => ['required', 'boolean'],
            'questionnaire.integration.api_use_case' => ['required', 'string', 'max:4000'],
            'questionnaire.integration.webhook_endpoint' => ['nullable', 'string', 'max:500'],
            'questionnaire.integration.hosting_region' => ['nullable', 'string', 'max:500'],

            'questionnaire.declarations' => ['required', 'array'],
            'questionnaire.declarations.no_anonymous_financial' => ['accepted'],
            'questionnaire.declarations.aml_sanctions' => ['accepted'],
            'questionnaire.declarations.end_user_activity_responsibility' => ['accepted'],
            'questionnaire.declarations.terms_of_service' => ['accepted'],

            'document_government_id' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document_certificate_incorporation' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document_director_id' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $q = $this->input('questionnaire', []);

            $entityType = data_get($q, 'entity.entity_type');
            if ($entityType === 'individual' && blank(data_get($q, 'entity.date_of_birth'))) {
                $validator->errors()->add(
                    'questionnaire.entity.date_of_birth',
                    __('Date of birth is required for individuals.'),
                );
            }

            if ($entityType === 'company' && blank(data_get($q, 'entity.registration_number'))) {
                $validator->errors()->add(
                    'questionnaire.entity.registration_number',
                    __('Company registration number is required for companies.'),
                );
            }

            if ($entityType === 'company' && blank(data_get($q, 'ownership.beneficial_owners'))) {
                $validator->errors()->add(
                    'questionnaire.ownership.beneficial_owners',
                    __('Beneficial owners (over 25%) are required for companies.'),
                );
            }

            $internal = (bool) data_get($q, 'platform.usage_internal', false);
            $platform = (bool) data_get($q, 'platform.usage_platform', false);
            if (! $internal && ! $platform) {
                $validator->errors()->add(
                    'questionnaire.platform.usage_internal',
                    __('Select at least one platform usage type.'),
                );
            }

            if ($platform) {
                foreach ([
                    'launch_estimate' => __('Estimated end users at launch is required when serving end users.'),
                    'end_users_have_wallets' => __('Please indicate whether end users will have wallets.'),
                    'agents_act_for_users' => __('Please indicate whether agents act on behalf of end users.'),
                    'funds_owner' => __('Please indicate who legally owns the funds.'),
                    'user_agent_interaction' => __('Describe how users interact with agents.'),
                ] as $field => $message) {
                    if (blank(data_get($q, "end_user_exposure.{$field}"))) {
                        $validator->errors()->add(
                            "questionnaire.end_user_exposure.{$field}",
                            $message,
                        );
                    }
                }
            }

            if (data_get($q, 'compliance.kyc_on_end_users') === 'yes') {
                if (blank(data_get($q, 'compliance.kyc_provider'))) {
                    $validator->errors()->add(
                        'questionnaire.compliance.kyc_provider',
                        __('KYC method/provider is required when you perform KYC on end users.'),
                    );
                }
                if (blank(data_get($q, 'compliance.kyc_data_collected'))) {
                    $validator->errors()->add(
                        'questionnaire.compliance.kyc_data_collected',
                        __('Describe data collected for end-user KYC.'),
                    );
                }
            }

            if (data_get($q, 'compliance.kyc_on_end_users') === 'no' && blank(data_get($q, 'compliance.kyc_no_explanation'))) {
                $validator->errors()->add(
                    'questionnaire.compliance.kyc_no_explanation',
                    __('Explain why you do not perform KYC on end users.'),
                );
            }

            $backend = (bool) data_get($q, 'integration.backend', false);
            $client = (bool) data_get($q, 'integration.client_side', false);
            if (! $backend && ! $client) {
                $validator->errors()->add(
                    'questionnaire.integration.backend',
                    __('Select at least one integration type.'),
                );
            }

            if (in_array($entityType, ['individual', 'sole_proprietor'], true) && ! $this->hasFile('document_government_id')) {
                $validator->errors()->add(
                    'document_government_id',
                    __('Government ID upload is required for this entity type.'),
                );
            }

            if ($entityType === 'company') {
                if (! $this->hasFile('document_certificate_incorporation')) {
                    $validator->errors()->add(
                        'document_certificate_incorporation',
                        __('Certificate of incorporation is required for companies.'),
                    );
                }
                if (! $this->hasFile('document_director_id')) {
                    $validator->errors()->add(
                        'document_director_id',
                        __('Director ID upload is required for companies.'),
                    );
                }
            }
        });
    }
}
