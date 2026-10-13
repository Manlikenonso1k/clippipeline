<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a social account's token cannot be refreshed and the user must reconnect. */
class NeedsReauthException extends RuntimeException {}
