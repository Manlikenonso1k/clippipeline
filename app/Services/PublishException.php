<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A publish failure carrying the platform's real error text and whether a retry
 * could help (5xx / 429 / network) or not (4xx, rejected media, bad state).
 */
class PublishException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public static function permanent(string $message): self
    {
        return new self($message, false);
    }

    public static function retryable(string $message): self
    {
        return new self($message, true);
    }

    /** Build from a failed HTTP response, keeping the platform's own error text. */
    public static function fromResponse(string $what, Response $response): self
    {
        $json = $response->json();
        $detail = null;

        if (is_array($json)) {
            $error = $json['error'] ?? null;
            $detail = is_array($error)
                ? ($error['error_user_msg'] ?? $error['message'] ?? null)
                : ($json['error_description'] ?? $json['message'] ?? (is_string($error) ? $error : null));
        }

        $text = $what.' (HTTP '.$response->status().'): '.($detail ?: mb_substr($response->body(), 0, 500));
        $retryable = $response->serverError() || $response->status() === 429;

        return new self(trim($text), $retryable);
    }
}
