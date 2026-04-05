<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTopupRequest;
use App\Http\Resources\Api\V1\TopupResource;
use App\Http\Responses\ApiErrorResponse;
use App\Models\BankLink;
use App\Models\Topup;
use App\Models\WalletAccount;
use App\Policies\TopupPolicy;
use App\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function __construct(
        private readonly TopupService $topupService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Topup::class);

        $query = Topup::query()->with(['walletAccount', 'bankLink']);

        if ($request->filled('wallet_account_id')) {
            $wallet = WalletAccount::query()->where('public_id', $request->string('wallet_account_id'))->first();
            if ($wallet === null) {
                return ApiErrorResponse::json('resource_not_found', detail: ['field' => 'wallet_account_id']);
            }

            $this->authorize('view', $wallet);
            $query->where('wallet_account_id', $wallet->getKey());
        }

        $topups = $query->orderByDesc('id')->cursorPaginate(50);

        return TopupResource::collection($topups)->response();
    }

    public function store(StoreTopupRequest $request): JsonResponse
    {
        $wallet = WalletAccount::query()
            ->where('public_id', $request->string('wallet_account_id'))
            ->firstOrFail();

        $bankLink = BankLink::query()
            ->where('public_id', $request->string('bank_link_id'))
            ->firstOrFail();

        if (! app(TopupPolicy::class)->create($request->user(), $wallet)) {
            return ApiErrorResponse::json('forbidden');
        }

        $topup = $this->topupService->createAchTopup(
            $wallet,
            $bankLink,
            (int) $request->input('amount_cents'),
            trim((string) $request->header('Idempotency-Key')),
            $request->filled('authorization_ledger_entry_id')
                ? (int) $request->input('authorization_ledger_entry_id')
                : null,
        );

        $topup->load(['walletAccount', 'bankLink']);

        return (new TopupResource($topup))->response()->setStatusCode(201);
    }

    public function show(Request $request, Topup $topup): JsonResponse
    {
        $this->authorize('view', $topup);

        $topup->load(['walletAccount', 'bankLink']);

        return (new TopupResource($topup))->response();
    }
}
