<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests;

use BenTools\TypedHttpClient\DataAwareResponse;
use BenTools\TypedHttpClient\DataAwareResponseStream;
use BenTools\TypedHttpClient\RequestContext;
use BenTools\TypedHttpClient\Tests\Fixtures\Todo;
use BenTools\TypedHttpClient\Tests\Fixtures\TodoFactory;
use BenTools\TypedHttpClient\TypedHttpClient;
use LogicException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

describe('TypedHttpClient', function () {
    it('falls back on Symfony\'s default client and on an untyped factory', function () {
        expect(new TypedHttpClient())->toBeInstanceOf(TypedHttpClient::class);
    });

    it('returns plain arrays when no factory is provided', function () {
        $client = new TypedHttpClient(innerClient: new MockHttpClient(new JsonMockResponse(todoPayload())));

        expect($client->request('GET', 'https://example.com/todos/1')->getData())->toBe(todoPayload());
    });

    it('accepts a closure as a factory', function () {
        $client = new TypedHttpClient(
            fn (ResponseInterface $response, bool $throw, RequestContext $context) => $context->url,
            new MockHttpClient(new JsonMockResponse()),
        );

        expect($client->request('GET', 'https://example.com/todos/1')->getData())
            ->toBe('https://example.com/todos/1')
        ;
    });

    it('wraps the inner response into a data aware response carrying the request context', function () {
        $client = new TypedHttpClient(new TodoFactory(), new MockHttpClient(new JsonMockResponse(todoPayload(5))));

        $response = $client->request('POST', 'https://example.com/todos', ['json' => todoPayload(5)]);

        expect($response)->toBeInstanceOf(DataAwareResponse::class)
            ->and($response->requestContext->method)->toBe('POST')
            ->and($response->requestContext->url)->toBe('https://example.com/todos')
            ->and($response->requestContext->options)->toBe(['json' => todoPayload(5)])
            ->and($response->getData())->toBeInstanceOf(Todo::class)
            ->id->toBe(5)
        ;
    });

    it('streams several responses, keyed by their typed counterpart', function () {
        $client = new TypedHttpClient(new TodoFactory(), new MockHttpClient([
            new JsonMockResponse(todoPayload(1)),
            new JsonMockResponse(todoPayload(2)),
        ]));
        $responses = [
            $client->request('GET', 'https://example.com/todos/1'),
            $client->request('GET', 'https://example.com/todos/2'),
        ];

        $stream = $client->stream($responses);
        $ids = [];
        foreach ($stream as $response => $chunk) {
            expect($response)->toBeInstanceOf(DataAwareResponse::class);
            if ($chunk->isLast()) {
                $ids[] = $response->getData()->id;
            }
        }

        expect($stream)->toBeInstanceOf(DataAwareResponseStream::class)
            ->and($ids)->toEqualCanonicalizing([1, 2])
        ;
    });

    it('streams a single response as well', function () {
        $client = new TypedHttpClient(new TodoFactory(), new MockHttpClient(new JsonMockResponse(todoPayload(9))));
        $response = $client->request('GET', 'https://example.com/todos/9');

        $ids = [];
        foreach ($client->stream($response) as $streamed => $chunk) {
            if ($chunk->isLast()) {
                $ids[] = $streamed->getData()->id;
            }
        }

        expect($ids)->toBe([9]);
    });

    it('refuses to stream responses it did not create', function () {
        $innerClient = new MockHttpClient(new JsonMockResponse(todoPayload()));
        $client = new TypedHttpClient(new TodoFactory(), $innerClient);
        $foreignResponse = $innerClient->request('GET', 'https://example.com/todos/1');

        expect(function () use ($client, $foreignResponse) {
            foreach ($client->stream([$foreignResponse]) as $ignored) {
                // The generator is lazy: it only blows up once iterated.
            }
        })->toThrow(LogicException::class, sprintf('Cannot stream a response that is not a %s.', DataAwareResponse::class));
    });

    it('derives a new client applying the given options', function () {
        $innerClient = new MockHttpClient($mockResponse = new JsonMockResponse(todoPayload()));
        $client = new TypedHttpClient(new TodoFactory(), $innerClient);

        $scopedClient = $client->withOptions(['base_uri' => 'https://api.example.com/v1/']);
        $scopedClient->request('GET', 'todos/1')->getData();

        expect($scopedClient)->toBeInstanceOf(TypedHttpClient::class)
            ->not->toBe($client)
            ->and($mockResponse->getRequestUrl())->toBe('https://api.example.com/v1/todos/1')
        ;
    });

    it('derives a new client using another factory, on the same transport', function () {
        $innerClient = new MockHttpClient([
            new JsonMockResponse(todoPayload(11)),
            new JsonMockResponse(todoPayload(12)),
        ]);
        $untypedClient = new TypedHttpClient(innerClient: $innerClient);

        $typedClient = $untypedClient->withFactory(new TodoFactory());
        $closureClient = $untypedClient->withFactory(
            fn (ResponseInterface $response, bool $throw) => $response->toArray($throw)['id'],
        );

        expect($typedClient->request('GET', 'https://example.com/todos/11')->getData())
            ->toBeInstanceOf(Todo::class)
            ->id->toBe(11)
            ->and($closureClient->request('GET', 'https://example.com/todos/12')->getData())->toBe(12)
        ;
    });
});
