<?php

namespace App\Services;

/** The resumable session URI is gone (HTTP 404); the job restarts the upload. */
class YouTubeSessionExpired extends PublishException
{
    public function __construct(string $message)
    {
        parent::__construct($message, true);
    }
}
