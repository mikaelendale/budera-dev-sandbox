<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTransferRequest;
use App\Http\Resources\Api\V1\TransferResource;
use App\Http\Responses\ApiErrorResponse;
use App\Models\Transfer;
use App\Models\WalletAccount;
use App\Policies\TransferPolicy;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transfer::class);

        $query = Transfer::query()->with(['fromWalletAccount', 'toWalletAccount']);

        if ($request->filled('wallet_account_id')) {
            $wallet = WalletAccount::query()->where('public_id', $request->string('wallet_account_id'))->first();
            if ($wallet === null) {
                return ApiErrorResponse::json('resource_not_found', detail: ['field' => 'wallet_account_id']);
            }

            $this->authorize('view', $wallet);
            $wid = $wallet->getKey();
            $query->where(function ($q) use ($wid): void {
                $q->where('from_wallet_account_id', $wid)->orWhere('to_wallet_account_id', $wid);
            });
        }

        $transfers = $query->orderByDesc('id')->cursorPaginate(50);

        return TransferResource::collection($transfers)->response();
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $from = WalletAccount::query()
            ->where('public_id', $request->string('from_wallet_account_id'))
            ->firstOrFail();

        $to = WalletAccount::query()
            ->where('public_id', $request->string('to_wallet_account_id'))
            ->firstOrFail();

        if (! app(TransferPolicy::class)->create($request->user(), $from, $to)) {
            return ApiErrorResponse::json('forbidden');
        }

        $transfer = $this->transferService->createBookTransfer(
            $from,
            $to,
            (int) $request->input('amount_cents'),
            trim((string) $request->header('Idempotency-Key')),
        );

        $transfer->load(['fromWalletAccount', 'toWalletAccount']);

        return (new TransferResource($transfer))->response()->setStatusCode(201);
    }

    public function show(Request $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);

        $transfer->load(['fromWalletAccount', 'toWalletAccount']);

        return (new TransferResource($transfer))->response();
    }
}
