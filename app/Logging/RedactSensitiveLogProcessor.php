<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactSensitiveLogProcessor implements ProcessorInterface
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_SUBSTRINGS = [
        'authorization',
        'password',
        'api_key',
        'secret',
        'bearer',
        'routing',
        'account_number',
        'routing_number',
        'encrypted_account',
        'encrypted_routing',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redactArray($record->context));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redactArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyStr = strtolower((string) $key);
            $redact = false;
            foreach (self::SENSITIVE_SUBSTRINGS as $frag) {
                if (str_contains($keyStr, $frag)) {
                    $redact = true;
                    break;
                }
            }
            if ($redact) {
                $out[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redactArray($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
