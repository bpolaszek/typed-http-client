<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests;

use BenTools\TypedHttpClient\ClosureDataFactory;
use BenTools\TypedHttpClient\RequestContext;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

describe('ClosureDataFactory', function () {
    it('forwards the response, the throw flag and the context to the wrapped closure', function () {
        $received = [];
        $factory = new ClosureDataFactory(
            function (ResponseInterface $response, bool $throw, RequestContext $context) use (&$received) {
                $received = ['response' => $response, 'throw' => $throw, 'context' => $context];

                return $response->toArray($throw)['id'];
            },
        );

        $response = innerResponse(new JsonMockResponse(todoPayload(42)));
        $context = new RequestContext('GET', 'https://example.com/todos/42');

        expect($factory($response, false, $context))->toBe(42)
            ->and($received['response'])->toBe($response)
            ->and($received['throw'])->toBeFalse()
            ->and($received['context'])->toBe($context)
        ;
    });
});
