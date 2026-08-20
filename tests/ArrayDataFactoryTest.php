<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests;

use BenTools\TypedHttpClient\ArrayDataFactory;
use BenTools\TypedHttpClient\RequestContext;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

describe('ArrayDataFactory', function () {
    $context = new RequestContext('GET', 'https://example.com/todos/1');

    it('decodes the response body into a plain array', function () use ($context) {
        $response = innerResponse(new JsonMockResponse(todoPayload(7, true)));

        expect((new ArrayDataFactory())($response, true, $context))->toBe(todoPayload(7, true));
    });

    it('honors the throw flag on erroneous responses', function () use ($context) {
        $factory = new ArrayDataFactory();
        $errorResponse = fn () => innerResponse(new JsonMockResponse(['error' => 'nope'], ['http_code' => 404]));

        expect($factory($errorResponse(), false, $context))->toBe(['error' => 'nope'])
            ->and(fn () => $factory($errorResponse(), true, $context))->toThrow(ClientException::class)
        ;
    });
});
