<?php

namespace Kelunik\Aerial\Internal;

use Kelunik\Aerial\Call;

/** @internal */
final class Service
{
    /** @var ServiceCall[] */
    private array $methods;

    public function __construct(array $methods)
    {
        $this->methods = $methods;
    }

    public function __call(string $name, array $arguments): Call
    {
        $serviceCall = $this->methods[\strtolower($name)] ?? throw new \BadMethodCallException('Unknown method: ' . $name);
        return $serviceCall->withArguments($arguments);
    }
}