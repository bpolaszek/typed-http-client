<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 *
 * @implements DataFactoryInterface<array<int|string, mixed>>
 */
final readonly class ArrayDataFactory implements DataFactoryInterface
{
    public function __invoke(ResponseInterface $response, bool $throw, RequestContext $context): array
    {
        return $response->toArray($throw);
    }
}
