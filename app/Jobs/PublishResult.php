<?php

namespace App\Jobs;

/** What a platform publish produced. */
final class PublishResult
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $url = null,
        public readonly ?string $thumbnail = null,
    ) {}
}
