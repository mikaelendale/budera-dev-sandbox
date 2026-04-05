<?php

namespace App\Services\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CorrelationId
{
    public const ATTRIBUTE = 'budera.correlation_id';

    public const HEADER = 'X-Correlation-Id';

    public static function bootstrap(Request $request): string
    {
        $header = $request->header(self::HEADER);
        if (is_string($header)) {
            $trimmed = trim($header);
            if ($trimmed !== '') {
                $id = Str::limit($trimmed, 128, '');
                $request->attributes->set(self::ATTRIBUTE, $id);

                return $id;
            }
        }

        $id = (string) Str::uuid();
        $request->attributes->set(self::ATTRIBUTE, $id);

        return $id;
    }

    public static function current(?Request $request = null): ?string
    {
        $request ??= request();
        if ($request === null) {
            return null;
        }

        $value = $request->attributes->get(self::ATTRIBUTE);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function fromRequestOrGenerate(): string
    {
        $request = request();
        if ($request !== null) {
            $existing = self::current($request);
            if ($existing !== null) {
                return $existing;
            }

            return self::bootstrap($request);
        }

        return (string) Str::uuid();
    }
}
