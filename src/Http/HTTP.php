<?php

namespace Kelunik\Aerial\Http;

use League\Uri\UriTemplate;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HTTP
{
    private string $method;
    private string $uri;

    public function __construct(string $method, string $uri)
    {
        $this->method = $method;
        $this->uri = $uri;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}