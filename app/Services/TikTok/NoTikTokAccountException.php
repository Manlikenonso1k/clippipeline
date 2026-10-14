<?php

namespace App\Services\TikTok;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The user has no active TikTok account (renders as 404 on the API). */
class NoTikTokAccountException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('Not found.');
    }
}
