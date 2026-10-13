<?php

namespace App\Services\Analytics;

use RuntimeException;

/** A platform reported a quota / rate limit: stop calling it for the rest of the run. */
class RateLimitedException extends RuntimeException {}
