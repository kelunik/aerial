<?php

namespace Kelunik\Aerial\Internal;

use Amp\Cancellation;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Serialization\Serializer;
use CuyZ\Valinor\Definition\FunctionDefinition;
use CuyZ\Valinor\Definition\ParameterDefinition;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\Type\Type;
use CuyZ\Valinor\Type\Types\InterfaceType;
use CuyZ\Valinor\Type\Types\UnresolvableType;
use Kelunik\Aerial\Call;
use Kelunik\Aerial\Http\Body;
use Kelunik\Aerial\Http\FieldValue;
use Kelunik\Aerial\Http\Header;
use Kelunik\Aerial\Http\HeaderValue;
use Kelunik\Aerial\Http\HTTP;
use Kelunik\Aerial\ServiceException;
use League\Uri\UriTemplate;

/** @internal */
final class ServiceCall implements Call
{
    private string $requestMethod;
    private UriTemplate $uriTemplate;

    private Type $bodyType;

    /** @var Header[] */
    private array $staticHeaders = [];

    private array $templateArguments = [];

    /** @var Header[] */
    private array $dynamicHeaders = [];

    private ?RequestBody $requestBody = null;

    private ?Request $request = null;
    private ?Response $response = null;

    public function __construct(
        private FunctionDefinition $definition,
        private HttpClient $httpClient,
        private Serializer $serializer,
        private TreeMapper $mapper,
    ) {
        $this->determineBodyType($this->definition->returnType());
        $this->determineRequestTarget();
        $this->determineHeaders();
    }

    private function determineBodyType(Type $returnType): void
    {
        if ($returnType instanceof UnresolvableType) {
            throw new \TypeError('Unresolvable type: ' . $returnType->getMessage());
        }

        if (!$returnType instanceof InterfaceType) {
            throw new \TypeError('Invalid return type, expected Kelunik\\Aerial\\Call<T>, got ' . $returnType->toString());
        }

        if ($returnType->className() !== Call::class) {
            throw new \TypeError('Invalid return type, expected Kelunik\\Aerial\\Call<T>, got ' . $returnType->toString());
        }

        $this->bodyType = $returnType->generics()['T'];
    }

    private function determineRequestTarget()
    {
        $http = self::attribute($this->definition, HTTP::class);
        if (!$http) {
            throw new ServiceException('Missing #[HTTP] attribute on ' . $this->definition->signature());
        }

        $this->requestMethod = $http->getMethod();
        $this->uriTemplate = new UriTemplate($http->getUri());
    }

    private static function attribute(
        ParameterDefinition|FunctionDefinition $definition,
        string $attributeClass
    ): ?object {
        if (!$definition->attributes()->has($attributeClass)) {
            return null;
        }

        $attributes = $definition->attributes()->ofType($attributeClass);
        if (\count($attributes) !== 1) {
            throw new ServiceException(\sprintf(
                'Invalid parameter %s, expected exactly one %s attribute',
                $definition->signature(),
                $attributeClass,
            ));
        }

        return $attributes[0];
    }

    private function determineHeaders()
    {
        $headers = $this->definition->attributes()->ofType(Header::class);
        foreach ($headers as $header) {
            $this->staticHeaders[] = $header;
        }
    }

    public function __clone(): void
    {
        $this->request = null;
        $this->response = null;
    }

    public function withArguments(array $arguments): self
    {
        $clone = clone $this;
        $clone->templateArguments = [];
        $clone->dynamicHeaders = [];
        $clone->requestBody = null;

        $formFields = [];

        $parameters = $clone->definition->parameters();
        foreach ($arguments as $key => $argument) {
            if (\is_numeric($key)) {
                $param = $parameters->at($key);
            } else {
                $param = $parameters->get($key);
            }

            if (self::attribute($param, Body::class)) {
                if (!$argument instanceof RequestBody) {
                    throw new ServiceException(\sprintf(
                        'Invalid argument type for #[Body] parameter (%s), must be %s',
                        $param->signature(),
                        RequestBody::class,
                    ));
                }

                if ($clone->requestBody !== null) {
                    throw new ServiceException(
                        'Duplicate request body, do you have multiple parameters with the #[Body] attribute?'
                    );
                }

                $clone->requestBody = $argument;
            } else if ($attribute = self::attribute($param, HeaderValue::class)) {
                if (!\is_string($argument)) {
                    throw new ServiceException(\sprintf(
                        'Invalid argument type for #[HeaderValue] parameter (%s), must be string',
                        $param->signature(),
                    ));
                }

                $clone->dynamicHeaders[] = new Header($attribute->getName(), $argument);
            } else if ($attribute = self::attribute($param, FieldValue::class)) {
                if (!\is_string($argument)) {
                    throw new ServiceException(\sprintf(
                        'Invalid argument type for #[Field] parameter (%s), must be string',
                        $param->signature(),
                    ));
                }

                // Temporarily store in a variable,
                // so we can set these fields on a custom FormBody instance set in another parameter.
                $formFields[] = [$attribute->getName(), $argument];
            } else {
                $clone->templateArguments[$param->name()] = $argument;
            }
        }

        if ($formFields) {
            $clone->requestBody ??= new FormBody();
            if (!$clone->requestBody instanceof FormBody) {
                throw new ServiceException(
                    'Unable to set form field parameter due to a custom request body'
                );
            }

            foreach ($formFields as [$name, $value]) {
                $clone->requestBody->addField($name, $value);
            }
        }

        $variableNames = $clone->uriTemplate->getVariableNames();
        foreach ($variableNames as $variableName) {
            if (!\array_key_exists($variableName, $clone->templateArguments)) {
                throw new ServiceException(\sprintf(
                    'Missing parameter $%s for variable expansion in %s at %s',
                    $variableName,
                    $clone->uriTemplate->getTemplate(),
                    $clone->definition->signature(),
                ));
            }
        }

        foreach ($clone->templateArguments as $name => $value) {
            if (!\in_array($name, $variableNames)) {
                throw new ServiceException(\sprintf(
                    'Parameter $%s isn\'t used in the URI template nor has any special attribute indicating its use at %s',
                    $name,
                    $clone->definition->signature(),
                ));
            }
        }

        return $clone;
    }

    public function execute(?Cancellation $cancellation = null): mixed
    {
        $uri = $this->uriTemplate->expand($this->templateArguments);
        $this->request = new Request($uri, $this->requestMethod);

        foreach ($this->staticHeaders as $header) {
            $this->request->addHeader($header->getName(), $header->getValue());
        }

        foreach ($this->dynamicHeaders as $header) {
            $this->request->addHeader($header->getName(), $header->getValue());
        }

        $this->response = $this->httpClient->request($this->request, $cancellation);
        $responseBody = $this->response->getBody()->buffer();

        return $this->mapper->map($this->bodyType->toString(), $this->serializer->unserialize($responseBody));
    }

    public function getRequest(): Request
    {
        return $this->request ?? throw new ServiceException('Request is not available yet, ensure execute() has been called.');
    }

    public function getResponse(): Response
    {
        return $this->response ?? throw new ServiceException('Response is not available yet, ensure execute() has been called.');
    }
}