<?php

namespace Kelunik\Aerial\Http;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class OPTIONS extends HTTP
{
    public function __construct(string $uri)
    {
        parent::__construct('OPTIONS', $uri);
    }
}