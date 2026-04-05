<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureBankPartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $pivotTable = config('permission.table_names.model_has_roles');
        $isBankPartner = DB::table($pivotTable)
            ->join('roles', 'roles.id', '=', $pivotTable.'.role_id')
            ->where($pivotTable.'.model_id', $user->getKey())
            ->where($pivotTable.'.model_type', $user->getMorphClass())
            ->where('roles.name', 'bank_partner')
            ->exists();

        if (! $isBankPartner) {
            abort(403);
        }

        return $next($request);
    }
}
