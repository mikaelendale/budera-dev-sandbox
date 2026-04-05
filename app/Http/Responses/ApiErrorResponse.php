<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ApiErrorResponse
{
    /**
     * @return array{error: array{code: string, message: string, detail: mixed, layer: string}}
     */
    public static function envelope(string $code, mixed $detail = null): array
    {
        $entry = config('api_errors.codes.'.$code);
        if (! is_array($entry)) {
            throw new InvalidArgumentException("Unknown API error code [{$code}]. Register it in config/api_errors.php.");
        }

        return [
            'error' => [
                'code' => $code,
                'message' => (string) $entry['message'],
                'detail' => $detail,
                'layer' => (string) $entry['layer'],
            ],
        ];
    }

    public static function json(string $code, ?int $status = null, mixed $detail = null): JsonResponse
    {
        $entry = config('api_errors.codes.'.$code);
        if (! is_array($entry)) {
            throw new InvalidArgumentException("Unknown API error code [{$code}]. Register it in config/api_errors.php.");
        }

        $httpStatus = $status ?? (int) ($entry['status'] ?? 400);

        return response()->json(self::envelope($code, $detail), $httpStatus);
    }

    /**
     * Merge extra top-level keys (e.g. resource snapshots) alongside the error envelope.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function jsonWith(string $code, array $extra, ?int $status = null, mixed $detail = null): JsonResponse
    {
        $entry = config('api_errors.codes.'.$code);
        if (! is_array($entry)) {
            throw new InvalidArgumentException("Unknown API error code [{$code}]. Register it in config/api_errors.php.");
        }

        $httpStatus = $status ?? (int) ($entry['status'] ?? 400);

        return response()->json(array_merge(self::envelope($code, $detail), $extra), $httpStatus);
    }
}
