# Symfony *Typed* HTTP Client:

[![CI Workflow](https://github.com/bpolaszek/typed-http-client/actions/workflows/ci.yaml/badge.svg)](https://github.com/bpolaszek/typed-http-client/actions/workflows/ci.yaml)
[![Code coverage](https://codecov.io/gh/bpolaszek/typed-http-client/branch/main/graph/badge.svg)](https://codecov.io/gh/bpolaszek/typed-http-client)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A thin, type-safe decorator around [Symfony's HTTP Client](https://symfony.com/doc/current/http_client.html) that hydrates responses into **real objects** instead of plain arrays.

You keep the whole `HttpClientInterface` API — including streaming — and get a `getData()` method on every response, returning whatever type your factory produces. Static analyzers (PHPStan, Psalm) follow the type all the way through, thanks to generics annotations.

```php
/** @var TypedHttpClient<Todo> $client */
$client = new TypedHttpClient(new TodoFactory());

$todo = $client->request('GET', 'https://jsonplaceholder.typicode.com/todos/1')->getData();
// $todo is a Todo object — and PHPStan knows it. ✨
```

## Installation

```bash
composer require bentools/typed-http-client
```

Requires PHP >= 8.5. The library only depends on `symfony/contracts`; bring your own `HttpClientInterface` implementation (e.g. `symfony/http-client`, used by default when installed).

## Usage

### Quick start with a closure

The fastest way to get typed data out of a response is to pass a closure. It receives the raw response, the `$throw` flag, and a [`RequestContext`](src/RequestContext.php):

```php
use BenTools\TypedHttpClient\RequestContext;
use BenTools\TypedHttpClient\TypedHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

$client = new TypedHttpClient(
    fn (ResponseInterface $response, bool $throw, RequestContext $context) => new Todo(...$response->toArray($throw)),
);

$todo = $client->request('GET', 'https://jsonplaceholder.typicode.com/todos/1')->getData();
```

### Dedicated factory classes

For anything reusable, implement [`DataFactoryInterface`](src/DataFactoryInterface.php). The `@implements` annotation is what makes generics click:

```php
use BenTools\TypedHttpClient\DataFactoryInterface;
use BenTools\TypedHttpClient\RequestContext;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @implements DataFactoryInterface<Todo>
 */
final readonly class TodoFactory implements DataFactoryInterface
{
    public function __invoke(ResponseInterface $response, bool $throw, RequestContext $context): Todo
    {
        $data = $response->toArray($throw);

        return new Todo($data['userId'], $data['id'], $data['title'], $data['completed']);
    }
}
```

```php
/** @var TypedHttpClient<Todo> $client */
$client = new TypedHttpClient(new TodoFactory());
```

### Discriminating by request

The `RequestContext` carries the `method`, `url` and `options` of the originating request, so a single factory can serve several endpoints:

```php
/**
 * @implements DataFactoryInterface<Todo|list<Todo>>
 */
final readonly class TodoEndpointFactory implements DataFactoryInterface
{
    public function __invoke(ResponseInterface $response, bool $throw, RequestContext $context): array|Todo
    {
        $data = $response->toArray($throw);

        return match (str_ends_with($context->url, '/todos')) {
            true => array_map(fn (array $item) => new Todo(...$item), $data),   // collection endpoint
            false => new Todo(...$data),                                        // single-item endpoint
        };
    }
}
```

### Deriving clients

`withFactory()` returns a new client with a different factory, on the same transport. Handy for scoping one client per resource:

```php
$baseClient = new TypedHttpClient(innerClient: HttpClient::createForBaseUri('https://api.example.com'));

/** @var TypedHttpClient<Todo> $todoClient */
$todoClient = $baseClient->withFactory(new TodoFactory());

/** @var TypedHttpClient<User> $userClient */
$userClient = $baseClient->withFactory(new UserFactory());
```

`withOptions()` works exactly like Symfony's, and keeps the factory:

```php
$authenticatedClient = $todoClient->withOptions(['auth_bearer' => $token]);
```

### Streaming

`stream()` works like Symfony's, and yields chunks keyed by the **typed** response:

```php
$responses = [
    $client->request('GET', 'https://jsonplaceholder.typicode.com/todos/1'),
    $client->request('GET', 'https://jsonplaceholder.typicode.com/todos/2'),
];

foreach ($client->stream($responses) as $response => $chunk) {
    if ($chunk->isLast()) {
        $todo = $response->getData(); // Todo object
    }
}
```

### Default behavior

Without a factory, the client falls back to [`ArrayDataFactory`](src/ArrayDataFactory.php): `getData()` simply returns the JSON-decoded body as an array.

```php
$client = new TypedHttpClient();
$data = $client->request('GET', 'https://jsonplaceholder.typicode.com/todos/1')->getData(); // plain array
```

### Error handling

`getData(bool $throw = true)` follows Symfony's convention: with `$throw = true` (the default), HTTP 4xx/5xx responses raise the usual `ClientException` / `ServerException`. Pass `false` to let your factory deal with erroneous payloads itself — the flag is forwarded to it.

Data is resolved **lazily** (nothing is decoded until you call `getData()`) and **memoized** (the factory runs at most once per response).

## Design notes

- `TypedHttpClient` implements `HttpClientInterface`: it is a drop-in replacement anywhere a Symfony HTTP client is expected.
- Responses returned by `request()` are `DataAwareResponse` instances, which decorate the inner response and implement `ResponseInterface` — every native method (`getStatusCode()`, `getHeaders()`, `toArray()`, …) is still available.
- `stream()` only accepts responses created by a `TypedHttpClient`, and throws a `LogicException` otherwise.

## Development

```bash
composer ci:check        # validate + phpstan + php-cs-fixer + tests with 100% coverage
composer tests:run       # pest
composer types:check     # phpstan (level max)
composer style:fix       # php-cs-fixer
```

## License

MIT.
