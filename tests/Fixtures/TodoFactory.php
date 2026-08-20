<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests\Fixtures;

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
