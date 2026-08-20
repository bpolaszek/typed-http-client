<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @return array{userId: int, id: int, title: string, completed: bool}
 */
function todoPayload(int $id = 1, bool $completed = false): array
{
    return [
        'userId' => 1,
        'id' => $id,
        'title' => sprintf('Todo #%d', $id),
        'completed' => $completed,
    ];
}

/**
 * Builds a real (non-mocked) response out of Symfony's test transport.
 */
function innerResponse(
    MockResponse $response = new JsonMockResponse(),
    string $method = 'GET',
    string $url = 'https://example.com/todos/1',
): ResponseInterface {
    return (new MockHttpClient($response))->request($method, $url);
}
