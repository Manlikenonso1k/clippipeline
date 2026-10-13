<?php

namespace App\Services\Connect;

use RuntimeException;

/** A connect/callback failure whose message is safe to show the user. */
class ConnectException extends RuntimeException {}
