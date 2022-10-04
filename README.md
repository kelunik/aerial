# kelunik/aerial

Build HTTP API clients using interface definitions.
Builds on top of [Valinor](https://github.com/CuyZ/Valinor).
It uses internal APIs, so use at your own risk for now.

```php
interface GitHub
{
    /** @return Call<GitHubUser> */
    #[GET('/users/{user}')]
    public function getUser(string $user): Call;
}

final class GitHubUser
{
    public string $login;
    public string $avatarUrl;
    public string $name;
}
```

```php
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
```

```php
$call = $github->getUser('kelunik');
$user = $call->execute();

var_dump($user->login);
var_dump($user->avatarUrl);
var_dump($user->name);

var_dump($call->getResponse()->getHeader('x-ratelimit-remaining'));
```