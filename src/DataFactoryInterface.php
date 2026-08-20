<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @template T
 */
interface DataFactoryInterface
{
    /**
     * @return T
     */
    public function __invoke(ResponseInterface $response, bool $throw, RequestContext $context): mixed;
}
