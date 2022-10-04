<?php

namespace Kelunik\Aerial\Http;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Header
{
    public function __construct(
        private string $name,
        private string $value
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}