<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class OAuthConnectionsController extends Controller
{
    public function edit(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes ?? [],
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'client_name' => $token->client?->name,
            ]);

        return Inertia::render('settings/oauth-connections', [
            'tokens' => $tokens,
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $model = $request->user()->tokens()->whereKey($token)->first();

        if ($model === null) {
            abort(404);
        }

        $model->revoke();

        return redirect()->back()->with('status', __('Access revoked.'));
    }
}
