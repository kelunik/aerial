<?php

namespace Kelunik\Aerial\Http;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class HEAD extends HTTP
{
    public function __construct(string $uri)
    {
        parent::__construct('HEAD', $uri);
    }
}