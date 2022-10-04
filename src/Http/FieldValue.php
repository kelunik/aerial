<?php

namespace Kelunik\Aerial\Http;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class FieldValue
{
    public function __construct(
        private string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }
}