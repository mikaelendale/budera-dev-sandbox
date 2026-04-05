<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LedgerEntryResource;
use App\Models\LedgerEntry;
use App\Models\WalletAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request, WalletAccount $walletAccount): JsonResponse
    {
        $this->authorize('view', $walletAccount);

        $entries = LedgerEntry::query()
            ->where('wallet_account_id', $walletAccount->getKey())
            ->orderByDesc('id')
            ->cursorPaginate(100);

        return LedgerEntryResource::collection($entries)->response();
    }
}
