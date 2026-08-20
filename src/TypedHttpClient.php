<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

use Closure;
use Generator;
use LogicException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use WeakMap;

use function is_iterable;

/**
 * @template T
 */
final readonly class TypedHttpClient implements HttpClientInterface
{
    private HttpClientInterface $innerClient;

    /**
     * @var DataFactoryInterface<T>
     */
    private DataFactoryInterface $factory;

    /**
     * @param Closure(ResponseInterface, bool, RequestContext): T|DataFactoryInterface<T> $factory
     */
    public function __construct(
        Closure|DataFactoryInterface $factory = new ArrayDataFactory(),
        ?HttpClientInterface $innerClient = null,
    ) {
        $this->factory = $factory instanceof Closure ? new ClosureDataFactory($factory) : $factory;
        $this->innerClient = $innerClient ?? HttpClient::create();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return DataAwareResponse<T>
     *
     * @throws TransportExceptionInterface
     */
    public function request(string $method, string $url, array $options = []): DataAwareResponse
    {
        $requestContext = new RequestContext($method, $url, $options);
        $response = $this->innerClient->request($method, $url, $options);

        return new DataAwareResponse($response, $requestContext, $this->factory);
    }

    /**
     * @param DataAwareResponse<T>|iterable<array-key, DataAwareResponse<T>> $responses
     *
     * @return DataAwareResponseStream<T>
     */
    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): DataAwareResponseStream
    {
        /** @var WeakMap<ResponseInterface, DataAwareResponse<T>> $innerToOuter */
        $innerToOuter = new WeakMap();
        $responses = is_iterable($responses) ? $responses : [$responses];

        return new DataAwareResponseStream(
            $this->streamChunks($this->registerAndYieldInnerResponses($responses, $innerToOuter), $innerToOuter, $timeout),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return clone ($this, [
            'innerClient' => $this->innerClient->withOptions($options),
        ]);
    }

    /**
     * @template U
     *
     * @param Closure(ResponseInterface, bool, RequestContext): U|DataFactoryInterface<U> $factory
     *
     * @return self<U>
     */
    public function withFactory(Closure|DataFactoryInterface $factory): self
    {
        return new self($factory, $this->innerClient);
    }

    /**
     * Registers the given responses into the map, and yields the responses to be streamed by the inner client.
     *
     * @param iterable<array-key, DataAwareResponse<T>>        $responses
     * @param WeakMap<ResponseInterface, DataAwareResponse<T>> $innerToOuter
     *
     * @return Generator<array-key, ResponseInterface>
     */
    private function registerAndYieldInnerResponses(iterable $responses, WeakMap $innerToOuter): Generator
    {
        foreach ($responses as $response) {
            self::assertDataAware($response);
            $innerToOuter[$response->innerResponse] = $response;

            yield $response->innerResponse;
        }
    }

    /**
     * Streams the inner responses back, keyed by their outer counterpart.
     *
     * @param iterable<array-key, ResponseInterface>           $responses
     * @param WeakMap<ResponseInterface, DataAwareResponse<T>> $innerToOuter
     *
     * @return Generator<DataAwareResponse<T>, ChunkInterface>
     */
    private function streamChunks(iterable $responses, WeakMap $innerToOuter, ?float $timeout): Generator
    {
        foreach ($this->innerClient->stream($responses, $timeout) as $response => $chunk) {
            yield $innerToOuter[$response] => $chunk;
        }
    }

    /**
     * @throws LogicException when the response was not created by a TypedHttpClient
     */
    private static function assertDataAware(mixed $response): void
    {
        if (!$response instanceof DataAwareResponse) {
            throw new LogicException(sprintf('Cannot stream a response that is not a %s.', DataAwareResponse::class));
        }
    }
}
