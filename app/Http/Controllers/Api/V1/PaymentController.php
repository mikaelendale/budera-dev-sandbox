<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Responses\ApiErrorResponse;
use App\Models\Payment;
use App\Models\WalletAccount;
use App\Policies\PaymentPolicy;
use App\Services\PaymentService;
use App\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()->with(['walletAccount']);

        if ($request->filled('wallet_account_id')) {
            $wallet = WalletAccount::query()->where('public_id', $request->string('wallet_account_id'))->first();
            if ($wallet === null) {
                return ApiErrorResponse::json('resource_not_found', detail: ['field' => 'wallet_account_id']);
            }

            $this->authorize('view', $wallet);
            $query->where('wallet_account_id', $wallet->getKey());
        }

        $payments = $query->orderByDesc('id')->cursorPaginate(50);

        return PaymentResource::collection($payments)->response();
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $wallet = WalletAccount::query()
            ->where('public_id', $request->string('wallet_account_id'))
            ->firstOrFail();

        if (! app(PaymentPolicy::class)->create($request->user(), $wallet)) {
            return ApiErrorResponse::json('forbidden');
        }

        $companyId = app(CompanyContext::class)->companyId();
        if ($companyId === null) {
            return ApiErrorResponse::json('company_context_required');
        }

        $amountCents = (int) $request->input('amount_cents');
        $payeeRef = $request->input('payee_ref');
        $category = $request->input('category');

        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        $payment = $this->paymentService->createOutboundAchPayment(
            $wallet,
            $amountCents,
            is_string($payeeRef) ? $payeeRef : null,
            is_string($category) ? $category : null,
            $idempotencyKey !== '' ? $idempotencyKey : null,
        );

        $payment->load('walletAccount');

        $resource = new PaymentResource($payment);

        return $resource->response()->setStatusCode(201);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->load(['walletAccount']);

        return (new PaymentResource($payment))->response();
    }
}
