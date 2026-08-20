<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @template T
 */
final class DataAwareResponse implements ResponseInterface
{
    /**
     * @var T
     */
    private mixed $data;
    private bool $initialized = false;

    /**
     * @param DataFactoryInterface<T> $factory
     */
    public function __construct(
        public readonly ResponseInterface $innerResponse,
        public readonly RequestContext $requestContext,
        public readonly DataFactoryInterface $factory,
    ) {}

    /**
     * @return T
     */
    public function getData(bool $throw = true): mixed
    {
        return match ($this->initialized) {
            true => $this->data,
            false => $this->resolveData($throw),
        };
    }

    public function getStatusCode(): int
    {
        return $this->innerResponse->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->innerResponse->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        return $this->innerResponse->getContent($throw);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        return $this->innerResponse->toArray($throw);
    }

    public function cancel(): void
    {
        $this->innerResponse->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->innerResponse->getInfo($type);
    }

    /**
     * @return T
     */
    private function resolveData(bool $throw = true): mixed
    {
        $this->data = ($this->factory)($this->innerResponse, $throw, $this->requestContext);
        $this->initialized = true;

        return $this->data;
    }
}
