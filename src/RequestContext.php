<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

final readonly class RequestContext
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $options = [],
    ) {}
}
