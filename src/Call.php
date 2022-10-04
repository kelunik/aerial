<?php

namespace Kelunik\Aerial;

use Amp\Cancellation;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

/**
 * @template T
 */
interface Call
{
    /**
     * @return T
     */
    public function execute(?Cancellation $cancellation = null): mixed;

    public function getRequest(): Request;

    public function getResponse(): Response;
}