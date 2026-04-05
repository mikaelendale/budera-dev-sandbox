<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\BankLink\BankLinkService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyBankLinkRequest;
use App\Http\Resources\Api\V1\BankLinkResource;
use App\Http\Responses\ApiErrorResponse;
use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;
use App\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BankLinkController extends Controller
{
    public function __construct(
        private readonly BankLinkService $bankLinkService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BankLink::class);

        $actor = $request->user();
        if ($actor === null) {
            return ApiErrorResponse::json('unauthenticated_api');
        }

        $hasCredentials = $request->filled('routing_number') && $request->filled('account_number');
        $hasHosted = $request->filled('end_user_id') || $request->filled('end_user_email');

        if ($hasCredentials && $hasHosted) {
            return ApiErrorResponse::json(
                'invalid_request',
                detail: 'Provide either bank credentials or hosted session fields, not both.',
            );
        }

        if (! $hasCredentials && ! $hasHosted) {
            return ApiErrorResponse::json(
                'invalid_request',
                detail: 'Provide bank credentials, or end_user_id / end_user_email for a hosted session.',
            );
        }

        $environment = $request->input('environment');
        if (! is_string($environment) || $environment === '') {
            $environment = app(CompanyContext::class)->environment();
        }
        if (! is_string($environment) || $environment === '') {
            $environment = 'sandbox';
        }

        if ($hasCredentials) {
            $request->validate([
                'routing_number' => ['required', 'string', 'regex:/^\d{9}$/'],
                'account_number' => ['required', 'string', 'min:4', 'max:32', 'regex:/^\d+$/'],
                'bank_slug' => ['nullable', 'string', 'max:64'],
                'environment' => ['nullable', 'string', Rule::in(['sandbox', 'live'])],
            ]);

            /** @var ApiKey|null $apiKey */
            $apiKey = $request->attributes->get('api_key');
            $companyId = $apiKey instanceof ApiKey ? (int) $apiKey->company_id : null;

            try {
                $link = $this->bankLinkService->startSession($actor, $environment, [
                    'routing_number' => (string) $request->input('routing_number'),
                    'account_number' => (string) $request->input('account_number'),
                    'bank_slug' => $request->input('bank_slug'),
                ], $companyId);
            } catch (InvalidArgumentException $e) {
                return ApiErrorResponse::json($e->getMessage());
            }

            return (new BankLinkResource($link))->response()->setStatusCode(201);
        }

        $request->validate([
            'end_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:end_user_email'],
            'end_user_email' => ['nullable', 'string', 'email', 'max:255', 'required_without:end_user_id'],
            'environment' => ['nullable', 'string', Rule::in(['sandbox', 'live'])],
        ]);

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        if (! $apiKey instanceof ApiKey) {
            return ApiErrorResponse::json('unauthenticated_api');
        }

        $company = Company::query()->find((int) $apiKey->company_id);
        if (! $company instanceof Company) {
            return ApiErrorResponse::json('server_error');
        }

        $endUser = null;
        if ($request->filled('end_user_id')) {
            $endUser = User::query()->find((int) $request->input('end_user_id'));
        } else {
            $email = (string) $request->input('end_user_email');
            $endUser = User::query()->where('email', $email)->first();
        }

        if (! $endUser instanceof User) {
            return ApiErrorResponse::json('end_user_not_found');
        }

        if (! $endUser->isAssociatedWithCompany($company)) {
            return ApiErrorResponse::json('end_user_not_in_company');
        }

        $result = $this->bankLinkService->createHostedSession($endUser, $company, $environment);
        $plain = $result['plain_session_token'];
        $link = $result['bankLink'];

        return response()->json([
            'session_token' => $plain,
            'hosted_url' => route('bank-link.show', ['sessionToken' => $plain]),
            'data' => (new BankLinkResource($link))->resolve(),
        ], 201);
    }

    public function show(Request $request, BankLink $bankLink): JsonResponse
    {
        $this->authorize('view', $bankLink);

        return (new BankLinkResource($bankLink))->response();
    }

    public function verify(VerifyBankLinkRequest $request, BankLink $bankLink): JsonResponse
    {
        $this->authorize('verify', $bankLink);

        $user = $request->user();
        if ($user === null) {
            return ApiErrorResponse::json('unauthenticated_api');
        }

        try {
            $link = $this->bankLinkService->verifyMicrodeposits(
                $bankLink,
                $user,
                (int) $request->input('amount_first_cents'),
                (int) $request->input('amount_second_cents'),
            );
        } catch (InvalidArgumentException $e) {
            $bankLink->refresh();

            return ApiErrorResponse::jsonWith(
                $e->getMessage(),
                ['bank_link' => (new BankLinkResource($bankLink))->resolve()],
            );
        }

        return (new BankLinkResource($link))->response();
    }

    public function destroy(Request $request, BankLink $bankLink): JsonResponse
    {
        $this->authorize('revoke', $bankLink);

        $user = $request->user();
        if ($user === null) {
            return ApiErrorResponse::json('unauthenticated_api');
        }

        try {
            $link = $this->bankLinkService->revoke($bankLink, $user);
        } catch (InvalidArgumentException $e) {
            return ApiErrorResponse::json($e->getMessage());
        }

        return (new BankLinkResource($link))->response();
    }
}
