<?php

namespace Tests\Feature;

use App\Logging\RedactSecrets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** COMPLIANCE A-06: tokens never reach the log file. */
class LogRedactionTest extends TestCase
{
    public function test_redacts_tokens_in_urls_headers_and_context(): void
    {
        $this->assertSame(
            'cURL error 28 for https://graph.facebook.com/v26.0/1/insights?metric=x&access_token=[redacted]&since=1',
            RedactSecrets::redact('cURL error 28 for https://graph.facebook.com/v26.0/1/insights?metric=x&access_token=EAABsecret123&since=1'),
        );
        $this->assertSame('Authorization: Bearer [redacted]', RedactSecrets::redact('Authorization: Bearer abc.def-123'));
        $this->assertSame('{"refresh_token":"[redacted]"}', RedactSecrets::redact('{"refresh_token":"rt-999"}'));
    }

    public function test_single_channel_writes_redacted_records(): void
    {
        $path = storage_path('logs/redaction-test.log');
        File::delete($path);
        config(['logging.channels.redaction_test' => [
            'driver' => 'single',
            'path' => $path,
            'tap' => [RedactSecrets::class],
        ]]);

        Log::channel('redaction_test')->warning('failed for https://x.test/?access_token=SECRET1', [
            'access_token' => 'SECRET2',
            'error' => 'client_secret=SECRET3',
        ]);

        $written = File::get($path);
        File::delete($path);
        $this->assertStringNotContainsString('SECRET', $written);
        $this->assertStringContainsString('[redacted]', $written);
    }
}
