<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use App\Models\IdempotencyKey;
use App\Tenancy\CompanyContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $rawKey = $request->header('Idempotency-Key');
        if (! is_string($rawKey) || trim($rawKey) === '') {
            return ApiErrorResponse::json('IDEMPOTENCY_KEY_REQUIRED');
        }

        $key = trim($rawKey);
        if (strlen($key) > 255) {
            return ApiErrorResponse::json('IDEMPOTENCY_KEY_INVALID');
        }

        $companyId = app(CompanyContext::class)->companyId();
        if ($companyId === null) {
            return ApiErrorResponse::json('company_context_required');
        }

        $fingerprint = $this->requestFingerprint($request);
        $lockName = 'idempotency:'.(string) $companyId.':'.hash('sha256', $key);

        return Cache::lock($lockName, 120)->block(10, function () use ($request, $next, $companyId, $key, $fingerprint): Response {
            $existing = IdempotencyKey::query()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->first();

            if ($existing !== null) {
                if ($existing->request_hash !== $fingerprint) {
                    return ApiErrorResponse::json('IDEMPOTENCY_KEY_CONFLICT');
                }

                /** @var array<string, mixed> $body */
                $body = is_array($existing->response_body) ? $existing->response_body : [];

                return response()->json($body, (int) $existing->response_status);
            }

            $response = $next($request);

            if ($this->isSuccessResponse($response)) {
                ['status' => $status, 'body' => $body] = $this->decodeResponse($response);

                try {
                    IdempotencyKey::query()->create([
                        'key' => $key,
                        'company_id' => $companyId,
                        'request_hash' => $fingerprint,
                        'response_status' => $status,
                        'response_body' => $body,
                        'created_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    if (($e->errorInfo[0] ?? '') !== '23000') {
                        throw $e;
                    }

                    $winner = IdempotencyKey::query()
                        ->where('company_id', $companyId)
                        ->where('key', $key)
                        ->firstOrFail();

                    if ($winner->request_hash !== $fingerprint) {
                        return ApiErrorResponse::json('IDEMPOTENCY_KEY_CONFLICT');
                    }

                    /** @var array<string, mixed> $replayBody */
                    $replayBody = is_array($winner->response_body) ? $winner->response_body : [];

                    return response()->json($replayBody, (int) $winner->response_status);
                }
            }

            return $response;
        });
    }

    private function isSuccessResponse(Response $response): bool
    {
        $code = $response->getStatusCode();

        return $code >= 200 && $code < 300;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function decodeResponse(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            return [
                'status' => $response->getStatusCode(),
                'body' => is_array($data) ? $data : ['value' => $data],
            ];
        }

        $content = $response->getContent();
        $decoded = json_decode((string) $content, true);

        return [
            'status' => $response->getStatusCode(),
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }

    private function requestFingerprint(Request $request): string
    {
        $input = $request->all();
        unset($input['idempotency_key']);

        $payload = [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'route_parameters' => $this->deepKsort($request->route()?->parameters() ?? []),
            'input' => $this->deepKsort($input),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function deepKsort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return [];
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->deepKsort($item), $value);
        }

        ksort($value);

        foreach ($value as $k => $v) {
            $value[$k] = $this->deepKsort($v);
        }

        return $value;
    }
}
