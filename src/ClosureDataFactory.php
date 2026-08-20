<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

use Closure;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @template T
 *
 * @implements DataFactoryInterface<T>
 */
final readonly class ClosureDataFactory implements DataFactoryInterface
{
    /**
     * @param Closure(ResponseInterface, bool, RequestContext): T $factory
     */
    public function __construct(
        private Closure $factory,
    ) {}

    /**
     * @return T
     */
    public function __invoke(ResponseInterface $response, bool $throw, RequestContext $context): mixed
    {
        return ($this->factory)($response, $throw, $context);
    }
}
