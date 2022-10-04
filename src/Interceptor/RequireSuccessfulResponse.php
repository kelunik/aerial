<?php

namespace Kelunik\Aerial\Interceptor;

use Amp\Cancellation;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Kelunik\Aerial\ServiceException;

final class RequireSuccessfulResponse implements ApplicationInterceptor
{
    public function request(Request $request, Cancellation $cancellation, DelegateHttpClient $httpClient): Response
    {
        $response = $httpClient->request($request, $cancellation);

        $status = $response->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new ServiceException('Invalid response status: ' . $status);
        }

        return $response;
    }
}