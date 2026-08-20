<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests;

use BenTools\TypedHttpClient\ArrayDataFactory;
use BenTools\TypedHttpClient\ClosureDataFactory;
use BenTools\TypedHttpClient\DataAwareResponse;
use BenTools\TypedHttpClient\RequestContext;
use BenTools\TypedHttpClient\Tests\Fixtures\Todo;
use BenTools\TypedHttpClient\Tests\Fixtures\TodoFactory;
use stdClass;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

describe('DataAwareResponse', function () {
    $context = new RequestContext('GET', 'https://example.com/todos/1');

    it('builds its data through the given factory', function () use ($context) {
        $response = new DataAwareResponse(
            innerResponse(new JsonMockResponse(todoPayload(3, true))),
            $context,
            new TodoFactory(),
        );

        expect($response->getData())->toBeInstanceOf(Todo::class)
            ->userId->toBe(1)
            ->id->toBe(3)
            ->title->toBe('Todo #3')
            ->completed->toBeTrue()
        ;
    });

    it('only calls the factory once, then serves the memoized data', function () use ($context) {
        $calls = 0;
        $response = new DataAwareResponse(
            innerResponse(new JsonMockResponse(todoPayload())),
            $context,
            new ClosureDataFactory(function () use (&$calls) {
                ++$calls;

                return new stdClass();
            }),
        );

        $first = $response->getData();

        expect($calls)->toBe(1)
            ->and($response->getData())->toBe($first)
            ->and($calls)->toBe(1)
        ;
    });

    it('hands the response, the throw flag and the request context over to the factory', function () use ($context) {
        $inner = innerResponse(new JsonMockResponse(todoPayload()));
        $received = [];
        $response = new DataAwareResponse(
            $inner,
            $context,
            new ClosureDataFactory(
                function (ResponseInterface $r, bool $throw, RequestContext $c) use (&$received) {
                    $received = ['response' => $r, 'throw' => $throw, 'context' => $c];

                    return null;
                },
            ),
        );

        $response->getData(false);

        expect($received['response'])->toBe($inner)
            ->and($received['throw'])->toBeFalse()
            ->and($received['context'])->toBe($context)
        ;
    });

    it('throws by default on erroneous responses, and stays silent when asked to', function () use ($context) {
        $errorResponse = fn () => new DataAwareResponse(
            innerResponse(new JsonMockResponse(['error' => 'nope'], ['http_code' => 500])),
            $context,
            new ArrayDataFactory(),
        );

        expect(fn () => $errorResponse()->getData())->toThrow(ServerException::class)
            ->and($errorResponse()->getData(false))->toBe(['error' => 'nope'])
        ;
    });

    it('delegates the transport methods to the inner response', function () use ($context) {
        $inner = innerResponse(
            new JsonMockResponse(todoPayload(), ['response_headers' => ['x-flavour' => 'vanilla']]),
        );
        $response = new DataAwareResponse($inner, $context, new ArrayDataFactory());

        expect($response->innerResponse)->toBe($inner)
            ->and($response->requestContext)->toBe($context)
            ->and($response->getStatusCode())->toBe(200)
            ->and($response->getHeaders())->toHaveKey('x-flavour', ['vanilla'])
            ->and($response->getContent())->toBe(json_encode(todoPayload()))
            ->and($response->toArray())->toBe(todoPayload())
            ->and($response->getInfo('http_method'))->toBe('GET')
            ->and($response->getInfo())->toHaveKey('url', 'https://example.com/todos/1')
        ;
    });

    it('forwards the throw flag of the transport methods', function () use ($context) {
        $errorResponse = fn () => new DataAwareResponse(
            innerResponse(new JsonMockResponse(['error' => 'nope'], ['http_code' => 404])),
            $context,
            new ArrayDataFactory(),
        );

        expect(fn () => $errorResponse()->getHeaders())->toThrow(ClientException::class)
            ->and(fn () => $errorResponse()->getContent())->toThrow(ClientException::class)
            ->and(fn () => $errorResponse()->toArray())->toThrow(ClientException::class)
            ->and($errorResponse()->getHeaders(false))->toHaveKey('content-type')
            ->and($errorResponse()->getContent(false))->toBe(json_encode(['error' => 'nope']))
            ->and($errorResponse()->toArray(false))->toBe(['error' => 'nope'])
        ;
    });

    it('cancels the inner response', function () use ($context) {
        $inner = innerResponse(new MockResponse('whatever'));
        $response = new DataAwareResponse($inner, $context, new ArrayDataFactory());

        $response->cancel();

        expect($response->getInfo('canceled'))->toBeTrue();
    });
});
