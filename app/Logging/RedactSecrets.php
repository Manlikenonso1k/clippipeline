<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * COMPLIANCE A-06: never write tokens, secrets or signed_request to the log.
 * HTTP client errors often embed the request URL (Meta Graph calls carry
 * `access_token` in the query string), so values of secret-looking query/body
 * keys and Bearer headers are masked before any handler sees the record.
 */
class RedactSecrets
{
    private const PATTERN = '/((?:access_token|refresh_token|input_token|client_secret|fb_exchange_token|signed_request|id_token|code_verifier)["\']?\s*[=:]\s*["\']?)[^&\s"\',}]+/i';

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if (method_exists($handler, 'pushProcessor')) {
                $handler->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                    message: self::redact($record->message),
                    context: self::redactArray($record->context),
                ));
            }
        }
    }

    public static function redact(string $text): string
    {
        $text = (string) preg_replace(self::PATTERN, '$1[redacted]', $text);

        return (string) preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[redacted]', $text);
    }

    /** @param array<mixed> $data */
    private static function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/token|secret|signed_request|password/i', $key)) {
                $data[$key] = '[redacted]';
            } elseif (is_string($value)) {
                $data[$key] = self::redact($value);
            } elseif (is_array($value)) {
                $data[$key] = self::redactArray($value);
            }
        }

        return $data;
    }
}
