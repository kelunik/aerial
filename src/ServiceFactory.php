<?php

namespace Kelunik\Aerial;

use Amp\Http\Client\HttpClient;
use Amp\Serialization\Serializer;
use CuyZ\Valinor\Definition\Repository\Reflection\CombinedAttributesRepository;
use CuyZ\Valinor\Definition\Repository\Reflection\ReflectionFunctionDefinitionRepository;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Type\Parser\Factory\LexingTypeParserFactory;
use CuyZ\Valinor\Type\Parser\Template\BasicTemplateParser;
use Kelunik\Aerial\Internal\Service;
use Kelunik\Aerial\Internal\ServiceCall;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;

final class ServiceFactory
{
    private ReflectionFunctionDefinitionRepository $reflection;

    private LazyLoadingValueHolderFactory $proxyFactory;

    public function __construct(
        private HttpClient $httpClient,
        private Serializer $serializer,
        private ?TreeMapper $mapper = null
    ) {
        $this->mapper ??= (new MapperBuilder())->flexible()->mapper();

        $this->reflection = new ReflectionFunctionDefinitionRepository(
            new LexingTypeParserFactory(new BasicTemplateParser()),
            new CombinedAttributesRepository()
        );

        $this->proxyFactory = new LazyLoadingValueHolderFactory();
    }

    public function build(string $class): object
    {
        return $this->proxyFactory->createProxy(
            $class,
            function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($class) {
                $methods = [];

                $reflectionClass = new \ReflectionClass($class);
                foreach ($reflectionClass->getMethods() as $method) {
                    $definition = $this->reflection->for($method->getClosure($proxy));
                    $methods[\strtolower($method->getName())] = new ServiceCall($definition, $this->httpClient, $this->serializer, $this->mapper);
                }

                $wrappedObject = new Service($methods);
                $initializer = null; // turning off further lazy initialization

                return true; // report success
            }
        );
    }
}