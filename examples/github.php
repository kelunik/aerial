<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\ResolveBaseUri;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Serialization\JsonSerializer;
use Amp\Serialization\Serializer;
use CuyZ\Valinor\Mapper\Source\Source;
use Kelunik\Aerial\Call;
use Kelunik\Aerial\Http\GET;
use Kelunik\Aerial\Interceptor\RequireSuccessfulResponse;
use Kelunik\Aerial\ServiceFactory;

require __DIR__ . '/../vendor/autoload.php';

interface GitHub
{
    /** @return Call<GitHubUser> */
    #[GET('/users{/user}')]
    public function getUser(string $user): Call;
}

final class GitHubUser
{
    public string $login;
    public string $avatarUrl;
    public string $name;
}

$httpClient = (new HttpClientBuilder())
    ->intercept(new ResolveBaseUri('https://api.github.com/'))
    ->intercept(new SetRequestHeader('user-agent', 'github.com/kelunik/aerial'))
    ->intercept(new RequireSuccessfulResponse())
    ->build();

$serializer = new class implements Serializer {
    private Serializer $json;

    public function __construct()
    {
        $this->json = JsonSerializer::withAssociativeArrays();
    }

    public function serialize($data): string
    {
        return $this->json->serialize($data);
    }

    public function unserialize(string $data): Source
    {
        return Source::array($this->json->unserialize($data))->camelCaseKeys();
    }
};

$github = (new ServiceFactory(
    $httpClient,
    $serializer,
))->build(GitHub::class);

$call = $github->getUser('kelunik');
$user = $call->execute();

var_dump($user->login);
var_dump($user->avatarUrl);
var_dump($user->name);

var_dump($call->getResponse()->getHeader('x-ratelimit-remaining'));