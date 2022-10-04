<?php

namespace Kelunik\Aerial\Http;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class POST extends HTTP
{
    public function __construct(string $uri)
    {
        parent::__construct('POST', $uri);
    }
}